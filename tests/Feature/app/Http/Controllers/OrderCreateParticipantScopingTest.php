<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers;

use App\Enums\OrderItemType;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class OrderCreateParticipantScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_create_an_order_for_the_selected_company(): void
    {
        $company = Company::factory()->active()->create();
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'company_id' => null,
        ]);
        $customer = $this->createParticipant(UserRole::CUSTOMER, $company);
        $employee = $this->createParticipant(UserRole::EMPLOYEE, $company);
        Notification::fake();

        $response = $this->actingAs($superAdministrator)
            ->postJson('/api/v1/orders', $this->orderPayload($customer, $employee, $company));

        $this->assertSuccessfulOrderLifecycle($response, $superAdministrator, $customer, $employee);
    }

    private function createParticipant(UserRole $role, Company $company, bool $active = true): User
    {
        $factory = User::factory();

        if ($active) {
            $factory = $factory->active();
        } else {
            $factory = $factory->inactive();
        }

        return $factory->create([
            'role' => $role->value,
            'company_id' => $company->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(User $customer, User $employee, ?Company $company = null): array
    {
        return $this->orderPayloadWithIds($customer->id, $employee->id, $company?->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayloadWithIds(int|string $customerId, int|string $employeeId, ?int $companyId = null): array
    {
        return array_filter([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'title' => 'Scoped order',
            'description' => 'Order used to verify company participant scoping.',
            'priority' => OrderPriority::NORMAL->value,
            'assigned_to' => $employeeId,
            'items' => [
                ['item_type' => OrderItemType::CylinderHead->value],
            ],
        ], static fn(mixed $value): bool => $value !== null);
    }

    private function assertSuccessfulOrderLifecycle(
        TestResponse $response,
        User         $creator,
        User         $customer,
        User         $employee,
    ): void
    {
        $response->assertCreated()
            ->assertJsonPath('order.customer.id', $customer->id)
            ->assertJsonPath('order.assigned_to.id', $employee->id)
            ->assertJsonPath('order.created_by.id', $creator->id)
            ->assertJsonPath('order.lifecycle_status', OrderLifecycleStatus::AwaitingReview->value);

        $order = Order::query()->latest('id')->firstOrFail();
        $history = $order->orderHistories()
            ->where('field_changed', OrderHistory::FIELD_LIFECYCLE_STATUS)
            ->sole();

        self::assertSame(OrderLifecycleStatus::Received->value, $history->getRawOriginal('old_value'));
        self::assertSame(OrderLifecycleStatus::AwaitingReview->value, $history->getRawOriginal('new_value'));
    }

    public function test_administrator_can_create_an_order_for_their_company(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $company->id,
        ]);
        $customer = $this->createParticipant(UserRole::CUSTOMER, $company);
        $employee = $this->createParticipant(UserRole::EMPLOYEE, $company);
        Notification::fake();

        $response = $this->actingAs($administrator)
            ->postJson('/api/v1/orders', $this->orderPayload($customer, $employee));

        $this->assertSuccessfulOrderLifecycle($response, $administrator, $customer, $employee);
    }

    public function test_employee_can_create_an_order_for_their_company(): void
    {
        $company = Company::factory()->active()->create();
        $employee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]);
        $customer = $this->createParticipant(UserRole::CUSTOMER, $company);
        Notification::fake();

        $response = $this->actingAs($employee)
            ->postJson('/api/v1/orders', $this->orderPayload($customer, $employee));

        $this->assertSuccessfulOrderLifecycle($response, $employee, $customer, $employee);
    }

    public function test_super_administrator_cannot_mix_participants_from_different_companies(): void
    {
        $selectedCompany = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'company_id' => null,
        ]);
        $customer = $this->createParticipant(UserRole::CUSTOMER, $selectedCompany);
        $employee = $this->createParticipant(UserRole::EMPLOYEE, $otherCompany);
        $orderCount = Order::query()->count();
        $itemCount = OrderItem::query()->count();
        $historyCount = OrderHistory::query()->count();

        $response = $this->actingAs($superAdministrator)
            ->postJson('/api/v1/orders', $this->orderPayload($customer, $employee, $selectedCompany));

        $response->assertUnprocessable();
        self::assertSame($orderCount, Order::query()->count());
        self::assertSame($itemCount, OrderItem::query()->count());
        self::assertSame($historyCount, OrderHistory::query()->count());
    }

    public function test_administrator_cannot_override_their_effective_company(): void
    {
        $actorCompany = Company::factory()->active()->create();
        $forgedCompany = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $actorCompany->id,
        ]);
        $customer = $this->createParticipant(UserRole::CUSTOMER, $forgedCompany);
        $employee = $this->createParticipant(UserRole::EMPLOYEE, $forgedCompany);

        $response = $this->actingAs($administrator)
            ->postJson('/api/v1/orders', $this->orderPayload($customer, $employee, $forgedCompany));

        $response->assertUnprocessable();
        self::assertSame(0, Order::query()->count());
    }

    public function test_order_create_rejects_a_wrong_role_assignee(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $company->id,
        ]);
        $customer = $this->createParticipant(UserRole::CUSTOMER, $company);
        $wrongRoleAssignee = $this->createParticipant(UserRole::ADMINISTRATOR, $company);

        $response = $this->actingAs($administrator)
            ->postJson('/api/v1/orders', $this->orderPayload($customer, $wrongRoleAssignee));

        $response->assertUnprocessable();
        self::assertSame(0, Order::query()->count());
    }

    public function test_order_create_rejects_an_inactive_customer(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $company->id,
        ]);
        $inactiveCustomer = $this->createParticipant(UserRole::CUSTOMER, $company, active: false);
        $employee = $this->createParticipant(UserRole::EMPLOYEE, $company);

        $response = $this->actingAs($administrator)
            ->postJson('/api/v1/orders', $this->orderPayload($inactiveCustomer, $employee));

        $response->assertUnprocessable();
        self::assertSame(0, Order::query()->count());
    }

    public function test_order_create_rejects_an_inactive_employee(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $company->id,
        ]);
        $customer = $this->createParticipant(UserRole::CUSTOMER, $company);
        $inactiveEmployee = $this->createParticipant(UserRole::EMPLOYEE, $company, active: false);

        $response = $this->actingAs($administrator)
            ->postJson('/api/v1/orders', $this->orderPayload($customer, $inactiveEmployee));

        $response->assertUnprocessable();
        self::assertSame(0, Order::query()->count());
    }

    public function test_order_create_rejects_a_soft_deleted_participant(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $company->id,
        ]);
        $deletedCustomer = $this->createParticipant(UserRole::CUSTOMER, $company);
        $deletedCustomer->delete();
        $employee = $this->createParticipant(UserRole::EMPLOYEE, $company);

        $response = $this->actingAs($administrator)
            ->postJson('/api/v1/orders', $this->orderPayload($deletedCustomer, $employee));

        $response->assertUnprocessable();
        self::assertSame(0, Order::query()->count());
    }

    public function test_order_create_rejects_malformed_participant_ids(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($administrator)
            ->postJson('/api/v1/orders', $this->orderPayloadWithIds('forged-customer-id', 'forged-employee-id'));

        $response->assertUnprocessable();
        self::assertSame(0, Order::query()->count());
    }
}
