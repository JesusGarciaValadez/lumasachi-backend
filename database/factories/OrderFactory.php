<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $staff = User::factory();

        return [
            'uuid' => (string) Str::uuid7(),
            'customer_id' => User::factory()->state(fn () => ['role' => UserRole::CUSTOMER]),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(OrderStatus::cases())->value,
            'priority' => $this->faker->randomElement(OrderPriority::cases())->value,
            'estimated_completion' => $this->faker->dateTimeBetween('now', '+30 days'),
            'actual_completion' => null,
            'notes' => null,
            'created_by' => $staff,
            'updated_by' => $staff,
            'assigned_to' => $staff,
        ];
    }

    /**
     * Use distinct staff users for created_by, updated_by, and assigned_to.
     */
    public function distinctStaff(): self
    {
        return $this->state(fn () => [
            'created_by' => User::factory()->state(['role' => UserRole::EMPLOYEE]),
            'updated_by' => User::factory()->state(['role' => UserRole::EMPLOYEE]),
            'assigned_to' => User::factory()->state(['role' => UserRole::EMPLOYEE]),
        ]);
    }

    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderStatus::Delivered->value,
                'actual_completion' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    public function open(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => OrderStatus::Open->value,
                'actual_completion' => null,
            ];
        });
    }

}
