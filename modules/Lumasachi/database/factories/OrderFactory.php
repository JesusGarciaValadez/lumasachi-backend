<?php

namespace Modules\Lumasachi\database\factories;

use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;

final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'customer_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement([
                OrderStatus::OPEN->value,
                OrderStatus::IN_PROGRESS->value,
                OrderStatus::READY_FOR_DELIVERY->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::PAID->value,
            ]),
            'priority' => $this->faker->randomElement([
                OrderPriority::LOW->value,
                OrderPriority::NORMAL->value,
                OrderPriority::HIGH->value,
                OrderPriority::URGENT->value,
            ]),
            'category_id' => Category::factory(),
            'estimated_completion' => $this->faker->dateTimeBetween('now', '+30 days'),
            'actual_completion' => null,
            'notes' => $this->faker->optional()->paragraph(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'assigned_to' => $this->faker->optional()->randomElement([User::factory()]),
        ];
    }

    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderStatus::DELIVERED->value,
                'actual_completion' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    public function open()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderStatus::OPEN->value,
                'actual_completion' => null,
            ];
        });
    }
}
