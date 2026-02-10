<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderMotorInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PublicOrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $company = Company::factory()->create();
        $employee = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]);
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
        ]);

        $this->order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'assigned_to' => $employee->id,
            'created_by' => $employee->id,
            'status' => OrderStatus::InProgress->value,
        ]);

        OrderMotorInfo::create([
            'order_id' => $this->order->id,
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => '2020',
            'down_payment' => 0,
            'total_cost' => 0,
            'is_fully_paid' => false,
        ]);
    }

    #[Test]
    public function it_returns_order_when_uuid_and_date_match(): void
    {
        $response = $this->postJson('/api/v1/orders/track', [
            'uuid' => $this->order->uuid,
            'created_date' => $this->order->created_at->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'order' => [
                    'uuid',
                    'title',
                    'status',
                    'motor_info',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_wrong_uuid(): void
    {
        $response = $this->postJson('/api/v1/orders/track', [
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'created_date' => $this->order->created_at->toDateString(),
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_wrong_date(): void
    {
        $response = $this->postJson('/api/v1/orders/track', [
            'uuid' => $this->order->uuid,
            'created_date' => '1999-01-01',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/orders/track', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['uuid', 'created_date']);
    }

    #[Test]
    public function it_does_not_require_authentication(): void
    {
        // No actingAs — anonymous request
        $response = $this->postJson('/api/v1/orders/track', [
            'uuid' => $this->order->uuid,
            'created_date' => $this->order->created_at->toDateString(),
        ]);

        $response->assertOk();
    }
}
