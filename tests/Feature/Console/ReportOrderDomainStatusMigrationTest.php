<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it reports unknown payment actors without unresolved lifecycle statuses', function (): void {
    $staff = reportOrderDomainStatusStaff();
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
        'assigned_to' => $staff->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'disposition_status' => null,
    ]);
    OrderPayment::factory()->create([
        'order_id' => $order->id,
        'created_by' => null,
    ]);

    $this->artisan('orders:domain-status-report')
        ->expectsOutputToContain('Orders requiring canonical status review: 0')
        ->expectsOutputToContain('Payments with unknown actor: 1')
        ->assertExitCode(0);
});

test('it accepts orders with explicit domain statuses', function (): void {
    $staff = reportOrderDomainStatusStaff();
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
        'assigned_to' => $staff->id,
        'lifecycle_status' => OrderLifecycleStatus::Received->value,
        'disposition_status' => null,
    ]);

    $this->artisan('orders:domain-status-report')
        ->expectsOutputToContain('Orders requiring canonical status review: 0')
        ->assertExitCode(0);
});

function reportOrderDomainStatusStaff(): User
{
    return User::factory()->create([
        'company_id' => Company::factory(),
        'role' => UserRole::EMPLOYEE->value,
    ]);
}
