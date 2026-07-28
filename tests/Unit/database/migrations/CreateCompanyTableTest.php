<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if companies table exists', function () {
    expect(Schema::hasTable('companies'))->toBeTrue();
});
it('checks if companies table has all required columns', function () {
    $expectedColumns = [
        'id',
        'uuid',
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
        'notes',
        'description',
        'is_active',
        'settings',
        'created_at',
        'updated_at',
    ];

    foreach ($expectedColumns as $column) {
        expect(Schema::hasColumn('companies', $column))->toBeTrue("Column '{$column}' does not exist in companies table");
    }
});
