<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    /** @var class-string<Company> */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $attributes = [
            'name' => fake()->company(),
            'is_active' => fake()->boolean(90),
        ];

        $centralConnectionName = (string)config('tenancy.database.central_connection', config('database.default'));

        if (Schema::connection($centralConnectionName)->hasColumn('companies', 'email')) {
            return [
                ...$attributes,
                'email' => fake()->unique()->companyEmail(),
                'phone' => fake()->phoneNumber(),
                'address' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => 'Test state',
                'postal_code' => fake()->postcode(),
                'country' => fake()->country(),
                'website' => fake()->optional()->url(),
                'description' => fake()->optional()->paragraph(),
            ];
        }

        return $attributes;
    }

    /**
     * Indicate that the company is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the company is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
