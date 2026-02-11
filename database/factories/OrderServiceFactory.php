<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\OrderService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderService>
 */
final class OrderServiceFactory extends Factory
{
    protected $model = OrderService::class;

    public function definition(): array
    {
        $base = $this->faker->randomFloat(2, 10, 500);
        $net = round($base + $this->faker->randomFloat(2, 0, 100), 2);

        return [
            'order_item_id' => OrderItem::factory(),
            'service_key' => $this->faker->unique()->lexify('svc.????????'),
            'measurement' => $this->faker->boolean(40) ? (string) $this->faker->numberBetween(1, 100) : null,
            'is_budgeted' => false,
            'is_authorized' => false,
            'is_completed' => false,
            'base_price' => $base,
            'net_price' => $net,
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
