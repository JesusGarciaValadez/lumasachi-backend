<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Company;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'description' => fake()->optional(0.8)->paragraph(), // 80% chance of having description
            'website' => fake()->optional(0.7)->url(), // 70% chance of having website
            'logo' => fake()->optional(0.6)->imageUrl(200, 200, 'business'), // 60% chance of having logo
            'is_active' => fake()->boolean(90), // 90% chance of being active
            'tax_id' => fake()->optional(0.7)->numerify('##-#######'), // 70% chance of having tax_id
            'contact_person' => fake()->optional(0.8)->name(), // 80% chance of having contact person
            'contact_email' => fake()->optional(0.8)->safeEmail(), // 80% chance of having contact email
            'contact_phone' => fake()->optional(0.8)->phoneNumber(), // 80% chance of having contact phone
            'notes' => fake()->optional(0.5)->text(200), // 50% chance of having notes
            'settings' => fake()->optional(0.9)->passthrough([
                'theme' => fake()->randomElement(['light', 'dark', 'auto']),
                'notifications' => fake()->boolean(),
                'timezone' => fake()->timezone(),
                'language' => fake()->randomElement(['en', 'es', 'fr', 'de']),
            ]), // 90% chance of having settings
        ];
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

    /**
     * Indicate that the company has no website.
     */
    public function withoutWebsite(): static
    {
        return $this->state(fn (array $attributes) => [
            'website' => null,
        ]);
    }

    /**
     * Indicate that the company has no logo.
     */
    public function withoutLogo(): static
    {
        return $this->state(fn (array $attributes) => [
            'logo' => null,
        ]);
    }

    /**
     * Indicate that the company has minimal information.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
            'website' => null,
            'logo' => null,
            'tax_id' => null,
            'contact_person' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'notes' => null,
            'settings' => null,
        ]);
    }

    /**
     * Configure the factory to create a company with complete information.
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => fake()->paragraphs(3, true),
            'notes' => fake()->paragraphs(2, true),
            'settings' => [
                'theme' => fake()->randomElement(['light', 'dark', 'auto']),
                'notifications' => fake()->boolean(),
                'timezone' => fake()->timezone(),
                'language' => fake()->randomElement(['en', 'es', 'fr', 'de']),
                'currency' => fake()->currencyCode(),
                'date_format' => fake()->randomElement(['Y-m-d', 'd/m/Y', 'm/d/Y']),
                'time_format' => fake()->randomElement(['H:i', 'h:i A']),
            ],
        ]);
    }
}
