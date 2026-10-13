<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

uses(RefreshDatabase::class);

it('does not expose tenant-local users through the central company registry', function (): void {
    $company = new Company();

    expect($company->getConnectionName())->toBe(config('tenancy.database.central_connection'))
        ->and(method_exists($company, 'users'))->toBeFalse()
        ->and(method_exists($company, 'activeUsers'))->toBeFalse();
});

it('does not associate tenant users with the central company registry', function (): void {
    $user = new User();

    expect($user->getFillable())->not->toContain('company_id')
        ->and($user->getAttributes())->not->toHaveKey('company_id')
        ->and(method_exists($user, 'company'))->toBeFalse();
});

it('does not serialize central company membership with a tenant user', function (): void {
    $userData = (new User())->toArray();

    expect($userData)->not->toHaveKey('company')
        ->and($userData)->not->toHaveKey('company_id');
});

it('requires tenant context when resolving a tenant user', function (): void {
    config(['tenancy.allow_legacy_model_connection' => false]);

    expect(fn(): ?string => (new User())->getConnectionName())
        ->toThrow(TenancyNotInitializedException::class);
});

it('checks if active status belongs to tenant-local users', function (): void {
    $activeUsers = User::factory()->count(3)->create([
        'is_active' => true,
    ]);

    $inactiveUsers = User::factory()->count(2)->create([
        'is_active' => false,
    ]);

    expect($activeUsers->every(static fn(User $user): bool => $user->is_active))->toBeTrue()
        ->and($inactiveUsers->every(static fn(User $user): bool => !$user->is_active))->toBeTrue();
});
