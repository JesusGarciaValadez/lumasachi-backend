<?php

namespace Tests\Unit\app\Models;

use App\Models\Order;
use App\Models\OrderMotorInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrderMotorInfoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_casts_and_remaining_balance(): void
    {
        $order = Order::factory()->createQuietly();

        $info = OrderMotorInfo::create([
            'order_id' => $order->id,
            'brand' => 'Nissan',
            'liters' => '2.5',
            'year' => '2018',
            'model' => 'Altima',
            'cylinder_count' => '4',
            'down_payment' => 1500.00,
            'total_cost' => 1252.80,
            'is_fully_paid' => false,
        ]);

        $this->assertSame(1500.00, (float) $info->down_payment);
        $this->assertSame(1252.80, (float) $info->total_cost);
        $this->assertFalse($info->is_fully_paid);

        // remaining_balance = max(0, total_cost - down_payment) => 0
        $this->assertSame(0.0, $info->remaining_balance);
    }
}
