<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderLifecycleStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderMotorInfo;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\OrderPaymentService;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(DuskTestCase::class, DatabaseTruncation::class);

beforeEach(function (): void {
    $this->seed(ServiceCatalogSeeder::class);

    $company = Company::factory()->create();
    $password = Hash::make('password');

    $this->employee = User::factory()->create([
        'company_id' => $company->id,
        'email' => 'dusk-approval-employee@example.com',
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::EMPLOYEE->value,
    ]);
    $this->customer = User::factory()->create([
        'company_id' => null,
        'email' => 'dusk-approval-customer@example.com',
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::CUSTOMER->value,
    ]);
    $this->otherCustomer = User::factory()->create([
        'company_id' => null,
        'email' => 'dusk-unrelated-customer@example.com',
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::CUSTOMER->value,
    ]);

    $this->order = Order::factory()->createQuietly([
        'assigned_to' => $this->employee->id,
        'created_by' => $this->employee->id,
        'customer_id' => $this->customer->id,
        'lifecycle_status' => OrderLifecycleStatus::AwaitingCustomerApproval->value,
        'title' => 'Dusk approval order',
        'updated_by' => $this->employee->id,
    ]);

    OrderMotorInfo::factory()->createQuietly([
        'brand' => 'Honda',
        'cylinder_count' => '4',
        'liters' => '2.0',
        'model' => 'Civic',
        'order_id' => $this->order->id,
        'year' => '2020',
    ]);

    $item = $this->order->items()->create([
        'is_received' => true,
        'item_type' => OrderItemType::EngineBlock->value,
    ]);

    $serviceKeys = orderApprovalBusinessServiceKeys();
    $catalog = ServiceCatalog::query()
        ->whereIn('service_key', $serviceKeys)
        ->get()
        ->keyBy('service_key');

    foreach ($serviceKeys as $serviceKey) {
        $service = $catalog->get($serviceKey);

        $item->services()->createQuietly([
            'base_price' => $service->base_price,
            'is_budgeted' => true,
            'is_authorized' => false,
            'is_completed' => false,
            'measurement' => $serviceKey === 'deck_assembled_4cyl' ? '20' : null,
            'net_price' => $service->net_price,
            'service_key' => $serviceKey,
        ]);
    }
});

test('customer can approve and staff can complete and deliver the order', function (): void {
    $this->browse(function (Browser $browser): void {
        $orderPath = '/orders/' . $this->order->uuid;

        $browser->loginAs($this->customer)
            ->visit($orderPath)
            ->waitFor('@order-approval-panel', 10)
            ->check('@order-approval-service-wash_block')
            ->check('@order-approval-service-weld_between_cylinders_qr25')
            ->check('@order-approval-service-replace_cam_bearings')
            ->type('@order-approval-down-payment', '300')
            ->assertSeeIn('@order-approval-panel', '1,880.00')
            ->assertSeeIn('@order-approval-panel', '2,180.80')
            ->click('@order-approval-submit')
            ->waitFor('@order-confirm-action')
            ->click('@order-confirm-action')
            ->waitUntilMissing('@order-approval-panel', 10)
            ->waitForTextIn('@order-status', 'Ready for Work', 10)
            ->assertSeeIn('@order-financial-summary', '2,180.80');

        $browser->loginAs($this->employee)
            ->visit($orderPath)
            ->waitFor('@order-completion-panel', 10)
            ->check('@order-completion-service-wash_block')
            ->check('@order-completion-service-replace_cam_bearings')
            ->click('@order-completion-submit')
            ->waitFor('@order-confirm-action')
            ->click('@order-confirm-action')
            ->waitForTextIn('@order-financial-summary', '1,252.80', 10)
            ->assertSeeIn('@order-service-row-wash_block', 'Yes')
            ->assertSeeIn('@order-service-row-replace_cam_bearings', 'Yes')
            ->assertSeeIn('@order-service-row-weld_between_cylinders_qr25', 'No')
            ->click('@order-ready-submit')
            ->waitFor('@order-confirm-action')
            ->click('@order-confirm-action')
            ->waitFor('@order-delivery-panel', 10)
            ->waitForTextIn('@order-status', 'Ready for Delivery', 10)
            ->assertSeeIn('@order-delivery-panel', '952.80')
            ->assertDisabled('@order-delivery-action');

        app(OrderPaymentService::class)->recordPayment(
            $this->order->fresh(),
            '1252.80',
            $this->employee,
        );

        $browser->visit($orderPath)
            ->waitFor('@order-delivery-panel', 10)
            ->waitForTextIn('@order-delivery-remaining', '0.00', 10)
            ->assertEnabled('@order-delivery-action')
            ->click('@order-delivery-action')
            ->waitFor('@order-confirm-action')
            ->click('@order-confirm-action')
            ->waitForTextIn('@order-status', 'Delivered', 10)
            ->waitForTextIn('@order-history-feed', 'Delivered', 10)
            ->assertSeeIn('@order-history-feed', 'Ready for Delivery');
    });
});

test('unrelated customer cannot see the approval action', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->loginAs($this->otherCustomer)
            ->visit('/orders/' . $this->order->uuid)
            ->assertMissing('@order-approval-panel')
            ->assertMissing('@order-approval-submit');
    });
});

/**
 * @return list<string>
 */
function orderApprovalBusinessServiceKeys(): array
{
    return [
        'wash_block',
        'weld_between_cylinders_qr25',
        'deck_assembled_4cyl',
        'replace_cam_bearings',
        'polish_camshaft_bars',
    ];
}
