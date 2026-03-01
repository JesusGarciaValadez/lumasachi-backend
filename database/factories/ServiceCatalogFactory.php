<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderItemType;
use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCatalog>
 */
final class ServiceCatalogFactory extends Factory
{
    protected $model = ServiceCatalog::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->lexify('svc_????????');

        return [
            'service_key' => $key,
            'service_name_key' => 'service_catalog.'.$key,
            'item_type' => $this->faker->randomElement(OrderItemType::getValues()),
            'base_price' => $this->faker->randomFloat(2, 50, 5000),
            'tax_percentage' => 16.00,
            'requires_measurement' => $this->faker->boolean(30),
            'is_active' => true,
            'display_order' => $this->faker->numberBetween(1, 50),
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function forItemType(OrderItemType $type): self
    {
        return $this->state(fn () => [
            'item_type' => $type->value,
        ]);
    }

    public function withMeasurement(): self
    {
        return $this->state(fn () => [
            'requires_measurement' => true,
        ]);
    }
}
