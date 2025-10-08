<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\OrderService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderService>
 */
class OrderServiceFactory extends Factory
{
    protected $model = OrderService::class;

    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'service_key' => 'test.service',
            'measurement' => null,
            'is_budgeted' => false,
            'is_authorized' => false,
            'is_completed' => false,
            'base_price' => 0,
            'net_price' => 0,
        ];
    }

    public function budgeted(): self
    {
        return $this->state(fn () => [
            'is_budgeted' => true,
        ]);
    }

    public function authorized(): self
    {
        return $this->state(fn () => [
            'is_authorized' => true,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn () => [
            'is_completed' => true,
        ]);
    }
}
