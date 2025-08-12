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
        $userId = User::factory()->create()->id;

        return [
            'customer_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(OrderStatus::cases()),
            'priority' => $this->faker->randomElement(OrderPriority::cases()),
            'category_id' => Category::factory(),
            'estimated_completion' => $this->faker->dateTimeBetween('now', '+30 days'),
            'actual_completion' => null,
            'notes' => $this->faker->optional()->paragraph(),
            'created_by' => $userId,
            'updated_by' => $userId,
            'assigned_to' => $userId,
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
