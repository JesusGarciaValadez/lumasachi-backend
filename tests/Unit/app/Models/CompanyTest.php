<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if company uses required traits', function () {
    $company = new Company();

    // Check for HasFactory trait
    expect(method_exists($company, 'factory'))->toBeTrue();
});
it('checks if fillable attributes are set correctly', function () {
    $company = new Company();
    $fillable = $company->getFillable();

    $expectedFillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'description',
        'website',
        'logo',
        'is_active',
        'tax_id',
        'contact_person',
        'contact_email',
        'contact_phone',
        'notes',
        'settings',
    ];

    expect($fillable)->toEqual($expectedFillable);
});
it('checks if casts attributes are set correctly', function () {
    $company = new Company();
    $casts = $company->getCasts();

    expect($casts)->toHaveKey('is_active');
    expect($casts)->toHaveKey('settings');
    expect($casts)->toHaveKey('created_at');
    expect($casts)->toHaveKey('updated_at');

    expect($casts['is_active'])->toEqual('boolean');
    expect($casts['settings'])->toEqual('json');
    $this->assertStringContainsString('datetime', $casts['created_at']);
    $this->assertStringContainsString('datetime', $casts['updated_at']);
});
it('checks if model table name is correct', function () {
    $company = new Company();

    expect($company->getTable())->toEqual('companies');
});
it('checks if users relationship is correct', function () {
    $company = Company::factory()->create();

    $users = User::factory()->count(3)->create(['company_id' => $company->id]);

    expect($company->users)->toHaveCount(3);
    expect($company->users->pluck('id')->sort()->values()->toArray())->toEqual($users->pluck('id')->sort()->values()->toArray());
});
it('checks if active users relationship is correct', function () {
    $company = Company::factory()->create();

    User::factory()->count(2)->create(['company_id' => $company->id, 'is_active' => true]);
    User::factory()->count(1)->create(['company_id' => $company->id, 'is_active' => false]);

    expect($company->activeUsers)->toHaveCount(2);
});
it('checks if active scope is correct', function () {
    Company::factory()->count(2)->active()->create();
    Company::factory()->count(1)->inactive()->create();

    expect(Company::active()->get())->toHaveCount(2);
});
it('checks if inactive scope is correct', function () {
    Company::factory()->count(2)->active()->create();
    Company::factory()->count(1)->inactive()->create();

    expect(Company::inactive()->get())->toHaveCount(1);
});
it('checks if full address accessor is correct', function () {
    $company = Company::factory()->create([
        'address' => '123 Main St',
        'city' => 'Sample City',
        'state' => 'State',
        'postal_code' => '12345',
        'country' => 'Country',
    ]);

    $expectedFullAddress = '123 Main St, Sample City, State, 12345, Country';

    expect($company->fullAddress)->toEqual($expectedFullAddress);
});
