<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemComponent;
use App\Models\OrderService;
use App\Models\ServiceCatalog;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks relationships and casts', function () {
    $order = Order::factory()->createQuietly();
    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_type' => OrderItemType::EngineBlock,
        'is_received' => true,
    ]);

    expect($item->is_received)->toBeTrue();
    expect($item->item_type)->toBe(OrderItemType::EngineBlock);
    expect($item->order->id)->toBe($order->id);

    // Components
    OrderItemComponent::create([
        'order_item_id' => $item->id,
        'component_name' => 'bearing_caps',
        'is_received' => true,
    ]);
    expect($item->components)->toHaveCount(1);

    // Services
    ServiceCatalog::create([
        'service_key' => 'wash_block_active',
        'service_name_key' => 'services.wash_block',
        'item_type' => OrderItemType::EngineBlock,
        'base_price' => 600.00,
        'tax_percentage' => 16.00,
        'requires_measurement' => false,
        'is_active' => true,
        'display_order' => 1,
    ]);

    OrderService::create([
        'order_item_id' => $item->id,
        'service_key' => 'wash_block_active',
        'is_budgeted' => true,
        'base_price' => 600.00,
        'net_price' => 696.00,
    ]);

    expect($item->services)->toHaveCount(1);
    expect($item->services->first()->service_key)->toBe('wash_block_active');
});
