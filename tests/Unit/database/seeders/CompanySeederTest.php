<?php

declare(strict_types=1);

use Database\Seeders\CompanySeeder;
use Illuminate\Support\Facades\DB;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if seeder creates companies', function () {
    app(CompanySeeder::class)->run();

    expect(DB::table('companies')->pluck('name')->all())->toContain(
        'Acme Corporation',
        'TechVentures Inc.',
        'Global Solutions Ltd.',
        'StartUp Hub',
        'Legacy Enterprises',
    );

    // Ensure that inactive companies exist
    expect(DB::table('companies')
        ->where('name', 'Legacy Enterprises')
        ->where('is_active', false)
        ->exists())->toBeTrue();
});
