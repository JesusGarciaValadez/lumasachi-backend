<?php

declare(strict_types=1);

use App\Models\CompanySetting;
use Database\Seeders\TenantDefaultsSeeder;
use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

it('matches the tenant company settings schema', function (): void {
    $settings = new CompanySetting();

    expect($settings->getTable())->toBe('company_settings')
        ->and($settings->getFillable())->toEqual([
            'name',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'website',
            'logo',
            'tax_id',
            'contact_person',
            'contact_email',
            'contact_phone',
            'locale',
            'email_from_address',
            'email_from_name',
            'description',
            'notes',
            'settings',
        ])
        ->and($settings->getCasts())->toHaveKey('settings', 'array');
});

it('refuses to resolve a tenant connection outside company context', function (): void {
    config(['tenancy.allow_legacy_model_connection' => false]);

    expect(fn(): ?string => (new CompanySetting())->getConnectionName())
        ->toThrow(TenancyNotInitializedException::class);
});

it('uses a reference-only seeder for tenant databases', function (): void {
    expect(config('tenancy.seeder_parameters.--class'))->toBe(TenantDefaultsSeeder::class);
});
