<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\OrderLifecycleStatus;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);
uses(Illuminate\Foundation\Testing\WithFaker::class);

beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
});
it('checks if index lists order histories', function () {
    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($user);

    OrderHistory::factory()->count(3)->create();

    $first = $this->getJson('/api/v1/history');

    $first->assertStatus(200)
        ->assertHeader('X-Cache', 'MISS');

    $v = (int)Cache::get('order_histories:version', 1);
    $filters = [
        'order_id' => null,
        'from_date' => null,
        'to_date' => null,
        'page' => 1,
        'per_page' => 15,
    ];
    ksort($filters);
    $signature = md5(json_encode($filters));
    $locale = Locale::normalize(config('app.locale'))?->value ?? Locale::SPANISH->value;
    expect(Cache::has("order_histories:index:v{$v}:locale:{$locale}:f:{$signature}"))->toBeTrue();

    $second = $this->getJson('/api/v1/history');
    $second->assertStatus(200)
        ->assertHeader('X-Cache', 'HIT');

    $content = $second->json();
    expect($content)->toBeArray();
    if (isset($content['data'])) {
        expect($content['data'])->toHaveCount(3);
    } else {
        expect($content)->toHaveCount(3);
    }
});
it('checks if store creates new order history', function () {
    $user = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->actingAs($user);

    $order = Order::factory()->createQuietly([
        'assigned_to' => $user->id,
    ]);
    $orderHistoryData = [
        'order_id' => $order->id,
        'field_changed' => 'lifecycle_status',
        'old_value' => null,
        'new_value' => OrderLifecycleStatus::Delivered->value,
        'comment' => $this->faker->sentence(),
    ];

    $v1 = (int)Cache::get('order_histories:version', 0);

    $response = $this->postJson('/api/v1/history', $orderHistoryData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'order_id', 'field_changed', 'old_value', 'new_value', 'comment', 'description', 'created_by', 'created_at'],
        ]);

    $v2 = (int)Cache::get('order_histories:version', 0);
    expect($v2)->toBe($v1 + 1, 'Order histories cache version should bump on create');

    // Verify database has the correct data (without description since it's a calculated field)
    $this->assertDatabaseHas('order_histories', $orderHistoryData);

    // Verify the description accessor works correctly in the response
    $responseData = $response->json('data');
    expect($responseData['description'])->toEqual('Lifecycle status set to: Delivered');
});
it('checks if show order history', function () {
    $orderHistory = OrderHistory::factory()->create();

    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($user);

    $first = $this->getJson('/api/v1/history/' . $orderHistory->uuid);

    $first->assertStatus(200)
        ->assertHeader('X-Cache', 'MISS')
        ->assertJsonStructure([
            'data' => ['id', 'order_id', 'field_changed', 'old_value', 'new_value', 'comment', 'description', 'created_by', 'created_at'],
        ]);

    $second = $this->getJson('/api/v1/history/' . $orderHistory->uuid);
    $second->assertStatus(200)->assertHeader('X-Cache', 'HIT');
});
it('checks if destroy order history', function () {
    $orderHistory = OrderHistory::factory()->create();

    $user = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value]);
    $this->actingAs($user);

    $v1 = (int)Cache::get('order_histories:version', 0);

    $response = $this->deleteJson('/api/v1/history/' . $orderHistory->uuid);

    $response->assertStatus(204);

    $v2 = (int)Cache::get('order_histories:version', 0);
    expect($v2)->toBe($v1 + 1, 'Order histories cache version should bump on delete');

    $this->assertModelMissing($orderHistory);
});
it('checks if order for order history', function () {
    $customer = User::factory()->create(['role' => UserRole::CUSTOMER->value]);
    $order = Order::factory()->createQuietly(['customer_id' => $customer->id]);
    $orderHistory = OrderHistory::factory()->create(['order_id' => $order->id]);

    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($user);

    $response = $this->getJson('/api/v1/history/' . $orderHistory->uuid . '/order/' . $orderHistory->order->uuid);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'order' => ['id', 'lifecycle_status'],
        ]);
});
it('checks if order attachments for order history', function () {
    $orderHistory = OrderHistory::factory()->create();
    $attachments = Attachment::factory()->count(2)->create(['attachable_id' => $orderHistory->order_id, 'attachable_type' => 'order']);

    $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    $this->actingAs($user);

    $response = $this->getJson('/api/v1/history/' . $orderHistory->uuid . '/order/' . $orderHistory->order->uuid . '/attachments');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'attachments' => [
                '*' => ['id', 'file_name', 'url'],
            ],
        ]);
});
