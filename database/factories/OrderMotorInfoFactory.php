<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderMotorInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderMotorInfo>
 */
final class OrderMotorInfoFactory extends Factory
{
    /**
     * @var array<int, array{brand: string, model: string, liters: string, cylinder_count: string}>
     */
    private const ENGINES = [
        ['brand' => 'Ford', 'model' => 'Fiesta', 'liters' => '1.6', 'cylinder_count' => '4'],
        ['brand' => 'Ford', 'model' => 'Focus', 'liters' => '2.0', 'cylinder_count' => '4'],
        ['brand' => 'Chevrolet', 'model' => 'Aveo', 'liters' => '1.6', 'cylinder_count' => '4'],
        ['brand' => 'Chevrolet', 'model' => 'Silverado', 'liters' => '5.3', 'cylinder_count' => '8'],
        ['brand' => 'Nissan', 'model' => 'Sentra', 'liters' => '1.8', 'cylinder_count' => '4'],
        ['brand' => 'Nissan', 'model' => 'Altima', 'liters' => '2.5', 'cylinder_count' => '4'],
        ['brand' => 'Volkswagen', 'model' => 'Jetta', 'liters' => '2.0', 'cylinder_count' => '4'],
        ['brand' => 'Volkswagen', 'model' => 'Beetle', 'liters' => '1.6', 'cylinder_count' => '4'],
        ['brand' => 'Toyota', 'model' => 'Corolla', 'liters' => '1.8', 'cylinder_count' => '4'],
        ['brand' => 'Honda', 'model' => 'Civic', 'liters' => '1.5', 'cylinder_count' => '4'],
        ['brand' => 'Dodge', 'model' => 'RAM', 'liters' => '5.7', 'cylinder_count' => '8'],
        ['brand' => 'Jeep', 'model' => 'Grand Cherokee', 'liters' => '3.6', 'cylinder_count' => '6'],
    ];

    protected $model = OrderMotorInfo::class;

    public function definition(): array
    {
        $engine = $this->faker->randomElement(self::ENGINES);

        return [
            'order_id' => Order::factory(),
            'brand' => $engine['brand'],
            'model' => $engine['model'],
            'liters' => $engine['liters'],
            'year' => (string) $this->faker->numberBetween(2000, 2025),
            'cylinder_count' => $engine['cylinder_count'],
            'down_payment' => $this->faker->randomFloat(2, 0, 5000),
            'total_cost' => 0,
            'is_fully_paid' => false,
            'center_torque' => null,
            'rod_torque' => null,
            'first_gap' => null,
            'second_gap' => null,
            'third_gap' => null,
            'center_clearance' => null,
            'rod_clearance' => null,
        ];
    }

    public function withMeasurements(): self
    {
        return $this->state(fn () => [
            'center_torque' => (string) $this->faker->numberBetween(60, 90),
            'rod_torque' => (string) $this->faker->randomFloat(1, 25, 45),
            'first_gap' => (string) $this->faker->randomFloat(3, 0.010, 0.025),
            'second_gap' => (string) $this->faker->randomFloat(3, 0.010, 0.025),
            'third_gap' => (string) $this->faker->randomFloat(3, 0.015, 0.040),
            'center_clearance' => (string) $this->faker->randomFloat(4, 0.0005, 0.003),
            'rod_clearance' => (string) $this->faker->randomFloat(4, 0.0005, 0.004),
        ]);
    }

    public function fullyPaid(): self
    {
        return $this->state(fn () => [
            'is_fully_paid' => true,
        ]);
    }
}
