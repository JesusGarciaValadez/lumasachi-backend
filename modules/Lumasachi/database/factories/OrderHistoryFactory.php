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
        $fields = [
            OrderHistory::FIELD_STATUS,
            OrderHistory::FIELD_PRIORITY,
            OrderHistory::FIELD_ASSIGNED_TO,
            OrderHistory::FIELD_TITLE,
            OrderHistory::FIELD_ESTIMATED_COMPLETION,
            OrderHistory::FIELD_ACTUAL_COMPLETION,
            OrderHistory::FIELD_NOTES,
            OrderHistory::FIELD_CATEGORY,
        ];

        $field = $this->faker->randomElement($fields);

        return [
            'order_id' => Order::factory(),
            'field_changed' => $field,
            'old_value' => $this->generateValueForField($field, true),
            'new_value' => $this->generateValueForField($field, false),
            'comment' => $this->faker->optional(0.7)->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Generate appropriate value based on field type
     */
    private function generateValueForField(string $field, bool $isOldValue): ?string
    {
        // Sometimes the old value can be null (for new fields)
        if ($isOldValue && $this->faker->boolean(20)) {
            return null;
        }

        switch ($field) {
            case OrderHistory::FIELD_STATUS:
                return $this->faker->randomElement(OrderStatus::cases())->value;

            case OrderHistory::FIELD_PRIORITY:
                return $this->faker->randomElement(OrderPriority::cases())->value;

            case OrderHistory::FIELD_ASSIGNED_TO:
                return $this->faker->boolean(80) ? (string) $this->faker->numberBetween(1, 10) : null;

            case OrderHistory::FIELD_TITLE:
                return $this->faker->sentence(3);

            case OrderHistory::FIELD_NOTES:
                return $this->faker->paragraph(2);

            case OrderHistory::FIELD_ESTIMATED_COMPLETION:
            case OrderHistory::FIELD_ACTUAL_COMPLETION:
                return $this->faker->dateTimeBetween('now', '+6 months')->format('Y-m-d H:i:s');

            case OrderHistory::FIELD_CATEGORY:
                return $this->faker->boolean(80) ? (string) $this->faker->numberBetween(1, 5) : null;

            default:
                return $this->faker->sentence();
        }
    }

    /**
     * State for status change
     */
    public function statusChange(?string $from = null, ?string $to = null): self
    {
        return $this->state(function (array $attributes) use ($from, $to) {
            return [
                'field_changed' => OrderHistory::FIELD_STATUS,
                'old_value' => $from ?? $this->faker->randomElement(OrderStatus::cases())->value,
                'new_value' => $to ?? $this->faker->randomElement(OrderStatus::cases())->value,
            ];
        });
    }

    /**
     * State for priority change
     */
    public function priorityChange(?string $from = null, ?string $to = null): self
    {
        return $this->state(function (array $attributes) use ($from, $to) {
            return [
                'field_changed' => OrderHistory::FIELD_PRIORITY,
                'old_value' => $from ?? $this->faker->randomElement(OrderPriority::cases())->value,
                'new_value' => $to ?? $this->faker->randomElement(OrderPriority::cases())->value,
            ];
        });
    }

    /**
     * State for assignment change
     */
    public function assignmentChange(?int $from = null, ?int $to = null): self
    {
        return $this->state(function (array $attributes) use ($from, $to) {
            return [
                'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
                'old_value' => $from ? (string) $from : null,
                'new_value' => $to ? (string) $to : (string) User::factory()->create()->id,
            ];
        });
    }
}
