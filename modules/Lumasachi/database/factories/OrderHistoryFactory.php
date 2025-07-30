<?php

namespace Modules\Lumasachi\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use App\Models\User;

final class OrderHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrderHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = OrderStatus::cases();
        $priorities = OrderPriority::cases();

        return [
            'order_id' => Order::factory(),
            'status_from' => $this->faker->randomElement($statuses)->value,
            'status_to' => $this->faker->randomElement($statuses)->value,
            'priority_from' => $this->faker->randomElement($priorities)->value,
            'priority_to' => $this->faker->randomElement($priorities)->value,
            'description' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
