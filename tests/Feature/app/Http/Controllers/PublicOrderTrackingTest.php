<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderMotorInfo;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as InertiaAssert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
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
});
it('returns order when uuid and date match', function () {
    $response = $this->withHeaders(['Accept-Language' => 'es'])->postJson('/api/v1/orders/track', [
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
});
it('returns populated public collections with stable shapes', function () {
    $item = $this->order->items()->createQuietly([
        'item_type' => 'engine_block',
        'is_received' => true,
    ]);
    $item->components()->createQuietly([
        'component_name' => 'camshaft',
        'is_received' => true,
    ]);

    $catalogItem = ServiceCatalog::factory()->createQuietly([
        'service_key' => 'wash_block',
        'service_name_key' => 'service_catalog.wash_block',
    ]);
    $item->services()->createQuietly([
        'service_key' => $catalogItem->service_key,
        'measurement' => '1',
        'is_budgeted' => true,
        'is_authorized' => true,
        'is_completed' => true,
        'base_price' => '100.00',
        'net_price' => '116.00',
    ]);

    $response = $this->withHeaders(['Accept-Language' => 'es'])->postJson('/api/v1/orders/track', [
        'uuid' => $this->order->uuid,
        'created_date' => $this->order->created_at->toDateString(),
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'order' => [
                'motor_info' => ['brand', 'liters', 'year', 'model', 'cylinder_count'],
                'items' => [
                    '*' => [
                        'item_type',
                        'item_type_label',
                        'is_received',
                        'components' => ['*' => ['component_name', 'component_key', 'component_label', 'is_received']],
                    ],
                ],
                'services' => [
                    '*' => [
                        'service_key',
                        'service_name',
                        'measurement',
                        'is_budgeted',
                        'is_authorized',
                        'is_completed',
                        'base_price',
                        'net_price',
                    ],
                ],
                'financials' => ['budgeted', 'authorized', 'completed', 'advance_payment', 'remaining_balance'],
            ],
        ])
        ->assertJsonPath('order.items.0.components.0.component_name', 'camshaft')
        ->assertJsonPath('order.items.0.components.0.component_key', 'camshaft')
        ->assertJsonPath('order.items.0.components.0.component_label', 'Árbol de levas')
        ->assertJsonPath('order.items.0.item_type_label', 'Block')
        ->assertJsonPath('order.services.0.service_name', __('service_catalog.wash_block'))
        ->assertJsonPath('order.financials.completed', '116.00')
        ->assertJsonMissingPath('order.items.0.id')
        ->assertJsonMissingPath('order.items.0.uuid')
        ->assertJsonMissingPath('order.items.0.components.0.id')
        ->assertJsonMissingPath('order.services.0.id')
        ->assertJsonMissingPath('order.services.0.uuid')
        ->assertJsonMissingPath('order.services.0.order_item_id')
        ->assertJsonMissingPath('order.services.0.notes');
});
it('returns the order history and attachments', function () {
    $history = $this->order->orderHistories()->create([
        'field_changed' => 'status',
        'old_value' => OrderStatus::ReadyForWork,
        'new_value' => OrderStatus::InProgress,
        'created_by' => $this->order->created_by,
    ]);
    $attachment = $this->order->attachments()->create([
        'file_name' => 'work-order.pdf',
        'file_path' => 'attachments/work-order.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'uploaded_by' => $this->order->created_by,
    ]);

    $response = $this->postJson('/api/v1/orders/track', [
        'uuid' => $this->order->uuid,
        'created_date' => $this->order->created_at->toDateString(),
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'order' => [
                'history' => [
                    '*' => [
                        'field_changed',
                        'created_at',
                    ],
                ],
                'attachments' => [
                    '*' => [
                        'file_name',
                        'mime_type',
                        'file_size',
                    ],
                ],
            ],
        ])
        ->assertJsonFragment([
            'field_changed' => 'status',
        ])
        ->assertJsonFragment([
            'file_name' => 'work-order.pdf',
        ]);

    $response->assertJsonMissingPath('order.customer')
        ->assertJsonMissingPath('order.id')
        ->assertJsonMissingPath('order.motor_info.down_payment')
        ->assertJsonMissingPath('order.motor_info.total_cost')
        ->assertJsonMissingPath('order.motor_info.is_fully_paid')
        ->assertJsonMissingPath('order.history.0.old_value')
        ->assertJsonMissingPath('order.history.0.new_value')
        ->assertJsonMissingPath('order.history.0.created_by')
        ->assertJsonMissingPath('order.attachments.0.uuid')
        ->assertJsonMissingPath('order.attachments.0.file_path')
        ->assertJsonMissingPath('order.attachments.0.url')
        ->assertJsonMissingPath('order.attachments.0.uploaded_by');
});
it('returns empty services components history and attachment collections when none exist', function () {
    $this->order->items()->createQuietly([
        'item_type' => 'engine_block',
        'is_received' => false,
    ]);

    $response = $this->postJson('/api/v1/orders/track', [
        'uuid' => $this->order->uuid,
        'created_date' => $this->order->created_at->toDateString(),
    ]);

    $response->assertOk()
        ->assertJsonPath('order.services', [])
        ->assertJsonPath('order.items.0.components', [])
        ->assertJsonPath('order.history', [])
        ->assertJsonPath('order.attachments', []);
});
it('returns stable values and localized status and priority labels', function () {
    app()->setLocale('es');
    $this->order->forceFill([
        'status' => OrderStatus::InProgress->value,
        'priority' => OrderPriority::URGENT->value,
    ])->saveQuietly();

    $response = $this->withHeaders(['Accept-Language' => 'es'])
        ->postJson('/api/v1/orders/track', [
            'uuid' => $this->order->uuid,
            'created_date' => $this->order->created_at->toDateString(),
        ]);

    $response->assertOk()
        ->assertJsonPath('order.status', OrderStatus::InProgress->value)
        ->assertJsonPath('order.status_label', 'En progreso')
        ->assertJsonPath('order.priority', OrderPriority::URGENT->value)
        ->assertJsonPath('order.priority_label', 'Urgente');
});
it('returns 404 for wrong uuid', function () {
    $response = $this->postJson('/api/v1/orders/track', [
        'uuid' => '00000000-0000-0000-0000-000000000000',
        'created_date' => $this->order->created_at->toDateString(),
    ]);

    $response->assertNotFound()->assertExactJson(['code' => 'orders.track_not_found', 'message' => 'Order not found.']);
});
it('returns 404 for wrong date', function () {
    $response = $this->postJson('/api/v1/orders/track', [
        'uuid' => $this->order->uuid,
        'created_date' => '1999-01-01',
    ]);

    $response->assertNotFound()->assertExactJson(['code' => 'orders.track_not_found', 'message' => 'Order not found.']);
});
it('returns the same generic not found response for a mismatched uuid and date pair', function () {
    $otherOrder = Order::factory()->createQuietly([
        'created_at' => '1999-01-01 00:00:00',
    ]);

    $response = $this->postJson('/api/v1/orders/track', [
        'uuid' => $this->order->uuid,
        'created_date' => $otherOrder->created_at->toDateString(),
    ]);

    $response->assertNotFound()->assertExactJson(['code' => 'orders.track_not_found', 'message' => 'Order not found.']);
});
it('validates required fields', function () {
    $response = $this->postJson('/api/v1/orders/track', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['uuid', 'created_date']);
});
it('validates malformed tracking values', function () {
    $response = $this->postJson('/api/v1/orders/track', [
        'uuid' => 'not-a-uuid',
        'created_date' => 'not-a-date',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['uuid', 'created_date']);
});
it('rate limits repeated tracking requests', function () {
    $payload = [
        'uuid' => $this->order->uuid,
        'created_date' => $this->order->created_at->toDateString(),
    ];

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson('/api/v1/orders/track', $payload)->assertOk();
    }

    $this->postJson('/api/v1/orders/track', $payload)->assertTooManyRequests();
});
it('does not require authentication', function () {
    // No actingAs — anonymous request
    $response = $this->postJson('/api/v1/orders/track', [
        'uuid' => $this->order->uuid,
        'created_date' => $this->order->created_at->toDateString(),
    ]);

    $response->assertOk();
});
test('guests can open the public tracking page without order props', function () {
    $this->get(route('web.orders.track'))
        ->assertOk()
        ->assertInertia(fn(InertiaAssert $page) => $page
            ->component('Orders/Track')
            ->missing('order')
            ->missing('capabilities')
        );
});
