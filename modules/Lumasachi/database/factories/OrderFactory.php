<?php

namespace Modules\Lumasachi\database\factories;

use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
                Order::STATUS_OPEN,
                Order::STATUS_IN_PROGRESS,
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_PAID,
            ]),
            'priority' => $this->faker->randomElement([
                Order::PRIORITY_LOW,
                Order::PRIORITY_NORMAL,
                Order::PRIORITY_HIGH,
                Order::PRIORITY_URGENT,
            ]),
            'category' => $this->faker->word(),
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
                'status' => Order::STATUS_DELIVERED,
                'actual_completion' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    public function open()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Order::STATUS_OPEN,
                'actual_completion' => null,
            ];
        });
    }
}
