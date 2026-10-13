<?php

declare(strict_types=1);

use App\Models\Company;

it('checks if factory creates valid company', function () {
    $company = Company::factory()->make();

    expect($company)->toBeInstanceOf(Company::class);
    expect($company->name)->not->toBeNull();
});
it('checks if factory generates all required fields', function () {
    $company = Company::factory()->make();

    expect($company->name)->not->toBeNull()
        ->and($company->is_active)->toBeBool();
});

it('supports the central registry active status states', function () {
    expect(Company::factory()->active()->make()->is_active)->toBeTrue()
        ->and(Company::factory()->inactive()->make()->is_active)->toBeFalse();
});
