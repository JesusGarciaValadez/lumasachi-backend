<?php

namespace Database\Factories;

use App\Enums\OrderItemType;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'item_type' => $this->faker->randomElement(OrderItemType::getValues()),
            'is_received' => false,
        ];
    }

    public function received(): self
    {
        return $this->state(fn () => [
            'is_received' => true,
        ]);
    }
}
