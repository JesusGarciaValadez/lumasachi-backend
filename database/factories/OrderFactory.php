<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\Order;
use App\Models\Category;
use App\Models\User;

final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        $userId = User::factory()->create()->id;

        return [
            'uuid' => Str::uuid(),
            'customer_id' => User::factory()->create()->id,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(OrderStatus::cases()),
            'priority' => $this->faker->randomElement(OrderPriority::cases()),
            'category_id' => Category::factory(),
            'estimated_completion' => $this->faker->dateTimeBetween('now', '+30 days'),
            'actual_completion' => null,
            'notes' => null,
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
