<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderRefund> */
final class OrderRefundFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 1, 5000),
            'status' => RefundStatus::Requested->value,
            'reason' => fake()->sentence(),
            'requested_by' => User::factory(),
            'requested_at' => now(),
        ];
    }
}
