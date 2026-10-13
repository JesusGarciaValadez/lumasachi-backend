<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CentralSuperAdministrator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<CentralSuperAdministrator>
 */
final class CentralSuperAdministratorFactory extends Factory
{
    /** The current password being used by the factory. */
    protected static ?string $password;
    /** @var class-string<CentralSuperAdministrator> */
    protected $model = CentralSuperAdministrator::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid7()->toString(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'is_active' => true,
            'activated_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /** Indicate that the administrator's email address should be unverified. */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
