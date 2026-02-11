<?php

declare(strict_types=1);

namespace Tests\Unit\app\Models;

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemComponent;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_relationships_and_casts(): void
    {
        $order = Order::factory()->createQuietly();
        $item = OrderItem::create([
            'order_id' => $order->id,
            'item_type' => OrderItemType::EngineBlock,
            'is_received' => true,
        ]);

        $this->assertTrue($item->is_received);
        $this->assertSame(OrderItemType::EngineBlock, $item->item_type);
        $this->assertSame($order->id, $item->order->id);

        // Components
        OrderItemComponent::create([
            'order_item_id' => $item->id,
            'component_name' => 'bearing_caps',
            'is_received' => true,
        ]);
        $this->assertCount(1, $item->components);

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

        $this->assertCount(1, $item->services);
        $this->assertSame('wash_block_active', $item->services->first()->service_key);
    }
}
