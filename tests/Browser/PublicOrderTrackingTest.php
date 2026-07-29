<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\OrderHistoryEventType;
use App\Enums\OrderItemType;
use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\OrderMotorInfo;
use App\Models\ServiceCatalog;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class PublicOrderTrackingTest extends DuskTestCase
{
    use DatabaseTruncation;

    private Order $order;

    private string $attachmentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceCatalogSeeder::class);

        $this->order = Order::factory()->createQuietly([
            'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
            'title' => 'Dusk public order',
        ]);

        OrderMotorInfo::factory()->createQuietly([
            'brand' => 'Honda',
            'cylinder_count' => '4',
            'liters' => '2.0',
            'model' => 'Civic',
            'order_id' => $this->order->id,
            'year' => '2020',
        ]);

        $item = $this->order->items()->createQuietly([
            'is_received' => true,
            'item_type' => OrderItemType::EngineBlock->value,
        ]);
        $item->components()->createQuietly([
            'component_name' => 'bearing_caps',
            'is_received' => true,
        ]);
        $item->components()->createQuietly([
            'component_name' => 'camshaft',
            'is_received' => true,
        ]);

        foreach (['wash_block', 'replace_cam_bearings'] as $serviceKey) {
            $catalogItem = ServiceCatalog::query()
                ->where('service_key', $serviceKey)
                ->firstOrFail();

            $item->services()->createQuietly([
                'base_price' => $catalogItem->base_price,
                'is_authorized' => true,
                'is_budgeted' => true,
                'is_completed' => true,
                'net_price' => $catalogItem->net_price,
                'service_key' => $catalogItem->service_key,
            ]);
        }

        $this->order->orderHistories()->createQuietly([
            'created_by' => $this->order->created_by,
            'event_type' => OrderHistoryEventType::Lifecycle->value,
            'field_changed' => 'lifecycle_status',
            'new_value' => OrderLifecycleStatus::Delivered,
            'old_value' => OrderLifecycleStatus::ReadyForDelivery,
        ]);

        $attachment = $this->order->attachments()->create([
            'uuid' => (string)Str::uuid7(),
            'file_name' => 'dusk-inspection.pdf',
            'file_path' => $this->attachmentPath = 'dusk/' . Str::uuid7() . '.pdf',
            'file_size' => 24,
            'mime_type' => 'application/pdf',
            'uploaded_by' => $this->order->created_by,
        ]);
        Storage::disk('public')->put($attachment->file_path, '%PDF-1.4 dusk attachment');
    }

    protected function tearDown(): void
    {
        Storage::disk('public')->delete($this->attachmentPath);

        parent::tearDown();
    }

    public function test_guest_can_open_the_public_tracking_page(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/orders/track')
                ->waitFor('@public-order-tracking-page', 10)
                ->assertPresent('@public-order-tracking-page')
                ->assertPresent('@track-uuid')
                ->assertPresent('@track-date')
                ->assertPresent('@track-submit');
        });
    }

    public function test_guest_sees_a_validation_error_for_malformed_tracking_input(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/orders/track')
                ->type('@track-uuid', 'not-a-uuid')
                ->type('@track-date', '2026-07-29')
                ->click('@track-submit')
                ->waitFor('#track-uuid-error', 10)
                ->assertAttribute('@track-uuid', 'aria-invalid', 'true')
                ->assertSeeIn('#track-uuid-error', 'The order UUID must be a valid UUID.');
        });
    }

    public function test_guest_can_lookup_an_order_and_see_attachment_actions(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/orders/track')
                ->type('@track-uuid', $this->order->uuid);
            $this->setTrackingDate($browser, $this->order->created_at->toDateString());
            $browser->click('@track-submit')
                ->waitFor('@track-result', 10)
                ->assertSeeIn('@track-result', 'Dusk public order')
                ->assertSeeIn('@track-result', 'Delivered')
                ->assertSeeIn('@track-result', 'Honda')
                ->assertSeeIn('@track-result', '2.0')
                ->assertSeeIn('@track-result', '2020')
                ->assertSeeIn('@track-result', 'Civic')
                ->assertSeeIn('@track-result', '4')
                ->assertSeeIn('@track-result', 'Engine Block')
                ->assertSeeIn('@track-result', 'Bearing caps')
                ->assertSeeIn('@track-result', 'Camshaft')
                ->assertSeeIn('@track-result', 'Engine block wash')
                ->assertSeeIn('@track-result', 'Replace cam bearings')
                ->assertSeeIn('@track-result', 'Lifecycle status changed from Ready for Delivery to Delivered')
                ->assertSeeIn('@track-result', 'dusk-inspection.pdf')
                ->assertPresent('@attachment-preview-0')
                ->assertPresent('@attachment-download-0');
        });
    }

    public function test_guest_can_replace_a_result_and_error_with_a_later_lookup(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/orders/track')
                ->type('@track-uuid', $this->order->uuid);
            $this->setTrackingDate($browser, $this->order->created_at->toDateString());
            $browser->click('@track-submit')
                ->waitFor('@track-result', 10)
                ->assertSeeIn('@track-result', 'Dusk public order');

            $browser->type('@track-uuid', (string)Str::uuid7());
            $this->setTrackingDate($browser, '1999-01-01');
            $browser->click('@track-submit')
                ->waitFor('@track-error', 10)
                ->waitUntilMissing('@track-result', 10)
                ->assertSeeIn('@track-error', 'No order was found with those details.');

            $browser->type('@track-uuid', $this->order->uuid);
            $this->setTrackingDate($browser, $this->order->created_at->toDateString());
            $browser->click('@track-submit')
                ->waitFor('@track-result', 10)
                ->waitUntilMissing('@track-error', 10)
                ->assertSeeIn('@track-result', 'Dusk public order');
        });
    }

    private function setTrackingDate(Browser $browser, string $date): void
    {
        $browser->script("const input = document.querySelector('[dusk=\\\"track-date\\\"]'); input.value = '{$date}'; input.dispatchEvent(new Event('input', { bubbles: true }));");
    }
}
