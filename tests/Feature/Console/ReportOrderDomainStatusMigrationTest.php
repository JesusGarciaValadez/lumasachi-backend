<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\OrderLifecycleStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReportOrderDomainStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_unknown_payment_actors_without_unresolved_lifecycle_statuses(): void
    {
        $staff = $this->staff();
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
    }

    public function test_it_accepts_orders_with_explicit_domain_statuses(): void
    {
        $staff = $this->staff();
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
    }

    private function staff(): User
    {
        return User::factory()->create([
            'company_id' => Company::factory(),
            'role' => UserRole::EMPLOYEE->value,
        ]);
    }
}
