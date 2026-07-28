<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderService;
use App\Models\ServiceCatalog;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks catalog relation and casts', function () {
    $order = Order::factory()->createQuietly();
    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock,
        'is_received' => true,
    ]);

    $catalog = ServiceCatalog::create([
        'service_key' => 'wash_block_active',
        'service_name_key' => 'services.wash_block',
        'item_type' => OrderItemType::EngineBlock,
        'base_price' => 600.00,
        'tax_percentage' => 16.00,
        'requires_measurement' => false,
        'is_active' => true,
        'display_order' => 1,
    ]);

    $service = OrderService::create([
        'order_item_id' => $item->id,
        'service_key' => $catalog->service_key,
        'is_authorized' => true,
        'is_completed' => false,
        'base_price' => 600.00,
        'net_price' => 696.00,
    ]);

    expect($service->is_authorized)->toBeTrue();
    expect($service->is_completed)->toBeFalse();
    expect((float)$service->base_price)->toBe(600.00);
    expect((float)$service->net_price)->toBe(696.00);

    expect($service->catalogItem)->not->toBeNull();
    expect($service->catalogItem->service_key)->toBe($catalog->service_key);
});
