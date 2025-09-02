<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\OrderHistory;
use App\Models\Order;
use App\Models\User;

class OrderHistoryFactory extends Factory
{
    protected $model = OrderHistory::class;

    public function definition()
    {
        $field = $this->faker->randomElement([
            OrderHistory::FIELD_STATUS,
            OrderHistory::FIELD_PRIORITY,
            OrderHistory::FIELD_ASSIGNED_TO,
            OrderHistory::FIELD_TITLE,
        ]);

        $oldValue = null;
        $newValue = null;

        switch ($field) {
            case OrderHistory::FIELD_STATUS:
                $oldValue = $this->faker->randomElement(OrderStatus::cases());
                $newValue = $this->faker->randomElement(OrderStatus::cases());
                break;
            case OrderHistory::FIELD_PRIORITY:
                $oldValue = $this->faker->randomElement(OrderPriority::cases());
                $newValue = $this->faker->randomElement(OrderPriority::cases());
                break;
            case OrderHistory::FIELD_ASSIGNED_TO:
                $oldValue = User::factory();
                $newValue = User::factory();
                break;
            case OrderHistory::FIELD_TITLE:
                $oldValue = $this->faker->sentence;
                $newValue = $this->faker->sentence;
                break;
        }

        return [
            'uuid' => Str::uuid7(),
            'order_id' => Order::factory()->createQuietly(),
            'field_changed' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'comment' => $this->faker->sentence,
            'created_by' => User::factory(),
        ];
    }

    public function statusChange($oldStatus, $newStatus)
    {
        return $this->state(function (array $attributes) use ($oldStatus, $newStatus) {
            return [
                'field_changed' => OrderHistory::FIELD_STATUS,
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
            ];
        });
    }

    public function priorityChange($oldPriority, $newPriority)
    {
        return $this->state(function (array $attributes) use ($oldPriority, $newPriority) {
            return [
                'field_changed' => OrderHistory::FIELD_PRIORITY,
                'old_value' => $oldPriority,
                'new_value' => $newPriority,
            ];
        });
    }

    public function assignmentChange($oldAssignee, $newAssignee)
    {
        return $this->state(function (array $attributes) use ($oldAssignee, $newAssignee) {
            return [
                'field_changed' => OrderHistory::FIELD_ASSIGNED_TO,
                'old_value' => $oldAssignee,
                'new_value' => $newAssignee,
            ];
        });
    }
}
