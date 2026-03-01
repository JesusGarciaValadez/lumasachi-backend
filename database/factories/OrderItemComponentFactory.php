<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderItemType;
use App\Models\OrderItem;
use App\Models\OrderItemComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemComponent>
 */
final class OrderItemComponentFactory extends Factory
{
    protected $model = OrderItemComponent::class;

    public function definition(): array
    {
        $componentNames = array_merge(...array_map(
            fn (OrderItemType $type) => $type->getComponents(),
            OrderItemType::cases(),
        ));

        return [
            'order_item_id' => OrderItem::factory(),
            'component_name' => $this->faker->randomElement($componentNames),
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
