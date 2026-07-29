<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Order;
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

    public function test_guest_can_open_the_public_tracking_page(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/orders/track')
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
                ->waitFor('@track-error')
                ->assertPresent('@track-error');
        });
    }

    public function test_guest_can_lookup_an_order_and_see_attachment_actions(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/orders/track')
                ->type('@track-uuid', $this->order->uuid);
            $browser->script("const input = document.querySelector('[dusk=\\\"track-date\\\"]'); input.value = '{$this->order->created_at->toDateString()}'; input.dispatchEvent(new Event('input', { bubbles: true }));");
            $browser->click('@track-submit')
                ->waitFor('@track-result')
                ->assertSee('Dusk public order')
                ->assertPresent('@attachment-preview-0')
                ->assertPresent('@attachment-download-0');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->order = Order::factory()->createQuietly([
            'title' => 'Dusk public order',
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
}
