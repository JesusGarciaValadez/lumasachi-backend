<?php

declare(strict_types=1);

use App\Models\Company;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('checks if company uses required traits', function () {
    expect(Company::factory())->toBeInstanceOf(CompanyFactory::class);
});
it('checks if fillable attributes are set correctly', function () {
    $company = new Company();
    $fillable = $company->getFillable();

    $expectedFillable = [
        'name',
        'is_active',
    ];

    expect($fillable)->toEqual($expectedFillable);
});
it('checks if casts attributes are set correctly', function () {
    $company = new Company();
    $casts = $company->getCasts();

    expect($casts)->toHaveKey('is_active')
        ->and($casts['is_active'])->toEqual('boolean')
        ->and($casts['created_at'])->toContain('datetime')
        ->and($casts['updated_at'])->toContain('datetime');
});
it('checks if model table name is correct', function () {
    $company = new Company();

    expect($company->getTable())->toEqual('companies');
});
it('maps the tenant through the immutable company uuid', function () {
    $company = new Company();
    $relation = $company->tenant();

    expect($relation)->toBeInstanceOf(BelongsTo::class)
        ->and($relation->getForeignKeyName())->toBe('uuid')
        ->and($relation->getOwnerKeyName())->toBe('id');
});
it('checks if active scope filters on registry status', function () {
    expect(Company::active()->getBindings())->toEqual([true]);
});
it('checks if inactive scope filters on registry status', function () {
    expect(Company::inactive()->getBindings())->toEqual([false]);
});
