<?php

namespace Tests\Feature\app\Observers;

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderService;
use App\Models\OrderMotorInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderLiquidationObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_recalculates_total_cost_on_service_completion_and_sets_full_payment(): void
    {
        $order = Order::factory()->createQuietly();
        $info = OrderMotorInfo::create([
            'order_id' => $order->id,
            'down_payment' => 1500.00,
            'total_cost' => 0,
            'is_fully_paid' => false,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'item_type' => OrderItemType::ENGINE_BLOCK->value,
        ]);

        // Two services, completed, with net prices
        $s1 = OrderService::factory()->create([
            'order_item_id' => $item->id,
            'is_completed' => false,
            'net_price' => 600.40,
        ]);
        $s2 = OrderService::factory()->create([
            'order_item_id' => $item->id,
            'is_completed' => false,
            'net_price' => 652.40,
        ]);

        // Mark them completed → triggers recalc
        $s1->update(['is_completed' => true]);
        $s2->update(['is_completed' => true]);

        $info->refresh();
        $this->assertSame(1252.8, (float) $info->total_cost);
        $this->assertTrue($info->is_fully_paid); // 1500 >= 1252.8
    }

    #[Test]
    public function it_updates_is_fully_paid_when_down_payment_changes(): void
    {
        $order = Order::factory()->createQuietly();
        $info = OrderMotorInfo::create([
            'order_id' => $order->id,
            'down_payment' => 100.00,
            'total_cost' => 200.00,
            'is_fully_paid' => false,
        ]);

        // Increase down payment so it covers total cost
        $info->update(['down_payment' => 250.00]);

        $this->assertTrue($info->fresh()->is_fully_paid);
    }
}
