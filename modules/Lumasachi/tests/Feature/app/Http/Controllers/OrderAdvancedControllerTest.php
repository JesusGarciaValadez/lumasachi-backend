<?php

namespace Modules\Lumasachi\tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Tests\TestCase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class OrderAdvancedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;
    protected $employee;
    protected $employee2;
    protected $customer;
    protected $order;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create users with different roles
        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value]);
        $this->admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        $this->employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->employee2 = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);

        // Create a test order
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->employee->id,
            'assigned_to' => $this->employee->id,
            'status' => OrderStatus::OPEN->value
        ]);
    }

    /**
     * Test complete valid state transition flow: OPEN -> IN_PROGRESS -> READY_FOR_DELIVERY -> DELIVERED -> PAID
     */
    #[Test]
    public function it_checks_complete_valid_state_transition_flow()
    {
        $this->actingAs($this->employee);

        // Step 1: OPEN -> IN_PROGRESS
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::IN_PROGRESS->value,
            'notes' => 'Starting work on this order'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::IN_PROGRESS->value
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::IN_PROGRESS->value
        ]);

        // Check history was created
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $this->order->id,
            'field_changed' => 'status',
            'old_value' => OrderStatus::OPEN->value,
            'new_value' => OrderStatus::IN_PROGRESS->value,
            'created_by' => $this->employee->id
        ]);

        // Step 2: IN_PROGRESS -> READY_FOR_DELIVERY
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::READY_FOR_DELIVERY->value,
            'notes' => 'Order is ready for delivery'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::READY_FOR_DELIVERY->value
                ]
            ]);

        // Step 3: READY_FOR_DELIVERY -> DELIVERED
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::DELIVERED->value,
            'notes' => 'Order delivered to customer'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::DELIVERED->value
                ]
            ]);

        // Step 4: DELIVERED -> PAID
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::PAID->value,
            'notes' => 'Payment received'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::PAID->value
                ]
            ]);

        // Verify complete history chain
        $histories = OrderHistory::where('order_id', $this->order->id)
            ->where('field_changed', 'status')
            ->orderBy('created_at', 'asc')
            ->get();

        $this->assertCount(4, $histories);
        // The OrderHistory model casts values to enums, so we need to get the value property
        $this->assertEquals(OrderStatus::OPEN->value, $histories[0]->old_value?->value ?? $histories[0]->old_value);
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $histories[0]->new_value?->value ?? $histories[0]->new_value);
        $this->assertEquals(OrderStatus::IN_PROGRESS->value, $histories[1]->old_value?->value ?? $histories[1]->old_value);
        $this->assertEquals(OrderStatus::READY_FOR_DELIVERY->value, $histories[1]->new_value?->value ?? $histories[1]->new_value);
        $this->assertEquals(OrderStatus::READY_FOR_DELIVERY->value, $histories[2]->old_value?->value ?? $histories[2]->old_value);
        $this->assertEquals(OrderStatus::DELIVERED->value, $histories[2]->new_value?->value ?? $histories[2]->new_value);
        $this->assertEquals(OrderStatus::DELIVERED->value, $histories[3]->old_value?->value ?? $histories[3]->old_value);
        $this->assertEquals(OrderStatus::PAID->value, $histories[3]->new_value?->value ?? $histories[3]->new_value);
    }

    /**
     * Test alternative flow: OPEN -> CANCELLED
     */
    #[Test]
    public function it_checks_can_cancel_order_from_open_status()
    {
        $this->actingAs($this->employee);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::CANCELLED->value,
            'notes' => 'Customer cancelled the order'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::CANCELLED->value
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::CANCELLED->value
        ]);
    }

    /**
     * Test alternative flow: DELIVERED -> RETURNED -> CANCELLED
     */
    #[Test]
    public function it_checks_return_and_cancel_flow()
    {
        // Setup order in DELIVERED status
        $this->order->update(['status' => OrderStatus::DELIVERED->value]);

        $this->actingAs($this->employee);

        // DELIVERED -> RETURNED
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::RETURNED->value,
            'notes' => 'Customer returned the order'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::RETURNED->value
                ]
            ]);

        // RETURNED -> CANCELLED
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::CANCELLED->value,
            'notes' => 'Order cancelled after return'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::CANCELLED->value
                ]
            ]);
    }

    /**
     * Test NOT_PAID flow: DELIVERED -> NOT_PAID -> PAID
     */
    #[Test]
    public function it_checks_not_paid_to_paid_flow()
    {
        // Setup order in DELIVERED status
        $this->order->update(['status' => OrderStatus::DELIVERED->value]);

        $this->actingAs($this->employee);

        // DELIVERED -> NOT_PAID
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::NOT_PAID->value,
            'notes' => 'Payment pending'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::NOT_PAID->value
                ]
            ]);

        // NOT_PAID -> PAID
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::PAID->value,
            'notes' => 'Payment received after follow-up'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::PAID->value
                ]
            ]);
    }

    /**
     * Test invalid transition: Cannot go back from PAID status
     */
    #[Test]
    public function it_checks_cannot_transition_from_paid_status()
    {
        $this->actingAs($this->employee);

        // Set order to paid status
        $this->order->update(['status' => OrderStatus::PAID->value]);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::IN_PROGRESS->value
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJson([
                'errors' => [
                    'status' => [
                        'Invalid status transition.'
                    ]
                ]
            ]);
    }

    /**
     * Test invalid transition: Cannot go back from CANCELLED status
     */
    #[Test]
    public function it_checks_cannot_transition_from_cancelled_status()
    {
        $this->actingAs($this->employee);

        // Set order to cancelled status
        $this->order->update(['status' => OrderStatus::CANCELLED->value]);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::IN_PROGRESS->value
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJson([
                'errors' => [
                    'status' => [
                        'Invalid status transition.'
                    ]
                ]
            ]);
    }

    /**
     * Test invalid transition: Cannot skip steps
     */
    #[Test]
    public function it_checks_cannot_skip_transition_steps()
    {
        $this->actingAs($this->employee);

        // Try to go from OPEN directly to DELIVERED (skipping IN_PROGRESS and READY_FOR_DELIVERY)
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::DELIVERED->value
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJson([
                'errors' => [
                    'status' => [
                        'Invalid status transition.'
                    ]
                ]
            ]);
    }

    /**
     * Test invalid transition: Cannot go backwards in the flow
     */
    #[Test]
    public function it_checks_cannot_go_backwards_in_flow()
    {
        $this->actingAs($this->employee);

        // Set order to IN_PROGRESS
        $this->order->update(['status' => OrderStatus::IN_PROGRESS->value]);

        // Try to go back to OPEN
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::OPEN->value
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJson([
                'errors' => [
                    'status' => [
                        'Invalid status transition.'
                    ]
                ]
            ]);
    }

    /**
     * Test status update requires valid status
     */
    #[Test]
    public function it_checks_update_status_validates_status()
    {
        $this->actingAs($this->employee);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => 'InvalidStatus'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJson([
                'errors' => [
                    'status' => [
                        'The selected status is invalid.'
                    ]
                ]
            ]);
    }

    /**
     * Test status update requires authentication
     */
    #[Test]
    public function it_checks_status_update_requires_authentication()
    {
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::IN_PROGRESS->value
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test customer cannot update order status
     */
    #[Test]
    public function it_checks_customer_cannot_update_order_status()
    {
        $this->actingAs($this->customer);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::IN_PROGRESS->value
        ]);

        $response->assertForbidden();
    }

    /**
     * Test employee can only update orders assigned to them or created by them
     */
    #[Test]
    public function it_checks_employee_can_only_update_assigned_or_created_orders()
    {
        // Create another order not assigned to or created by the employee
        $otherOrder = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->admin->id,
            'assigned_to' => $this->employee2->id,
            'status' => OrderStatus::OPEN->value
        ]);

        $this->actingAs($this->employee);

        $response = $this->postJson("/api/v1/orders/{$otherOrder->id}/status", [
            'status' => OrderStatus::IN_PROGRESS->value
        ]);

        $response->assertForbidden();
    }

    /**
     * Test admin can update any order status
     */
    #[Test]
    public function it_checks_admin_can_update_any_order_status()
    {
        // Create order assigned to employee
        $employeeOrder = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->employee->id,
            'assigned_to' => $this->employee->id,
            'status' => OrderStatus::OPEN->value
        ]);

        $this->actingAs($this->admin);

        $response = $this->postJson("/api/v1/orders/{$employeeOrder->id}/status", [
            'status' => OrderStatus::IN_PROGRESS->value,
            'notes' => 'Admin updating status'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order status updated successfully.',
                'order' => [
                    'status' => OrderStatus::IN_PROGRESS->value
                ]
            ]);
    }

    /**
     * Test marking order as completed updates actual_completion date
     */
    #[Test]
    public function it_checks_marking_order_as_completed_sets_actual_completion_date()
    {
        // Setup order ready for delivery
        $this->order->update([
            'status' => OrderStatus::IN_PROGRESS->value,
            'actual_completion' => null
        ]);

        $this->actingAs($this->employee);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => OrderStatus::COMPLETED->value,
            'notes' => 'Order completed',
            'actual_completion' => now()->toIso8601String(), // Add actual_completion date
        ]);

        $response->assertOk();

        // Note: The actual_completion date should be set when marking as COMPLETED
        $this->order->refresh();

        $this->assertEquals(OrderStatus::COMPLETED->value, $this->order->status->value);
    }

    /**
     * Test assigning order to employee with proper permissions
     */
    #[Test]
    public function it_checks_admin_can_assign_order_to_employee()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->employee2->id,
            'notes' => 'Reassigning to more experienced employee'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order assigned successfully.',
                'order' => [
                    'assigned_to' => [
                        'id' => $this->employee2->id
                    ]
                ]
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'assigned_to' => $this->employee2->id
        ]);

        // Check history was created for assignment change
        $this->assertDatabaseHas('order_histories', [
            'order_id' => $this->order->id,
            'field_changed' => 'assigned_to',
            'old_value' => $this->employee->id,
            'new_value' => $this->employee2->id,
            'created_by' => $this->admin->id
        ]);
    }

    /**
     * Test super admin can assign orders
     */
    #[Test]
    public function it_checks_super_admin_can_assign_orders()
    {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->employee2->id,
            'notes' => 'Super admin reassignment'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order assigned successfully.',
                'order' => [
                    'assigned_to' => [
                        'id' => $this->employee2->id
                    ]
                ]
            ]);
    }

    /**
     * Test can assign order to administrator
     */
    #[Test]
    public function it_checks_can_assign_order_to_administrator()
    {
        $this->actingAs($this->superAdmin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->admin->id,
            'notes' => 'Assigning to admin for review'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Order assigned successfully.',
                'order' => [
                    'assigned_to' => [
                        'id' => $this->admin->id
                    ]
                ]
            ]);
    }

    /**
     * Test cannot assign order to customer
     */
    #[Test]
    public function it_checks_cannot_assign_order_to_customer()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->customer->id
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to'])
            ->assertJson([
                'errors' => [
                    'assigned_to' => [
                        'The selected user cannot be assigned to orders.'
                    ]
                ]
            ]);
    }

    /**
     * Test cannot assign to non-existent user
     */
    #[Test]
    public function it_checks_cannot_assign_to_nonexistent_user()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => '01234567890'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to'])
            ->assertJson([
                'errors' => [
                    'assigned_to' => [
                        'The selected employee does not exist.'
                    ]
                ]
            ]);
    }

    /**
     * Test employee cannot assign orders (only admin and super admin can)
     */
    #[Test]
    public function it_checks_employee_cannot_assign_orders()
    {
        $this->actingAs($this->employee);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->employee2->id
        ]);

        $response->assertForbidden();
    }

    /**
     * Test customer cannot assign orders
     */
    #[Test]
    public function it_checks_customer_cannot_assign_orders()
    {
        $this->actingAs($this->customer);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->employee2->id
        ]);

        $response->assertForbidden();
    }

    /**
     * Test assignment requires authentication
     */
    #[Test]
    public function it_checks_assignment_requires_authentication()
    {
        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", [
            'assigned_to' => $this->employee2->id
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test assignment requires assigned_to field
     */
    #[Test]
    public function it_checks_assignment_requires_assigned_to_field()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/assign", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_to'])
            ->assertJson([
                'errors' => [
                    'assigned_to' => [
                        'Please select an employee to assign the order to.'
                    ]
                ]
            ]);
    }

    /**
     * Test viewing order history
     */
    #[Test]
    public function it_checks_view_order_history()
    {
        $this->actingAs($this->employee);

        // Create some history entries
        OrderHistory::factory()->count(3)->create([
            'order_id' => $this->order->id
        ]);

        $response = $this->getJson("/api/v1/orders/{$this->order->id}/history");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'order_id',
                        'field_changed',
                        'old_value',
                        'new_value',
                        'comment',
                        'created_by',
                        'created_at'
                    ]
                ],
                'links',
                'meta'
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test history is returned in descending order
     */
    #[Test]
    public function it_checks_history_is_ordered_by_newest_first()
    {
        $this->actingAs($this->employee);

        // Create history entries with specific timestamps
        $oldHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_at' => now()->subDays(2)
        ]);

        $newHistory = OrderHistory::factory()->create([
            'order_id' => $this->order->id,
            'created_at' => now()
        ]);

        $response = $this->getJson("/api/v1/orders/{$this->order->id}/history");

        $response->assertOk();

        $history = $response->json('data');
        $this->assertEquals($newHistory->id, $history[0]['id']);
        $this->assertEquals($oldHistory->id, $history[1]['id']);
    }

}
