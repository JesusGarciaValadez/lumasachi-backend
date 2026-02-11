<?php

declare(strict_types=1);

namespace Tests\Unit\app\Models;

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_catalog_relation_and_casts(): void
    {
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

        $this->assertTrue($service->is_authorized);
        $this->assertFalse($service->is_completed);
        $this->assertSame(600.00, (float) $service->base_price);
        $this->assertSame(696.00, (float) $service->net_price);

        $this->assertNotNull($service->catalogItem);
        $this->assertSame($catalog->service_key, $service->catalogItem->service_key);
    }
}
