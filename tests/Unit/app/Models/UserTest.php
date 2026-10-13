<?php

declare(strict_types=1);

use App\Models\User;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

test('tenant users have no central company identifier fillable or default', function (): void {
    $user = new User();

    expect($user->getFillable())->not->toContain('company_id')
        ->and($user->getAttributes())->not->toHaveKey('company_id');
});

test('tenant users do not define a relationship to the central company registry', function (): void {
    expect(method_exists(new User(), 'company'))->toBeFalse();
});

test('tenant users cannot resolve through the default connection without tenant context', function (): void {
    config(['tenancy.allow_legacy_model_connection' => false]);

    expect(fn(): ?string => (new User())->getConnectionName())
        ->toThrow(TenancyNotInitializedException::class);
});
