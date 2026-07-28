<?php

declare(strict_types=1);

use Database\Seeders\CompanySeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if seeder creates companies', function () {
    $this->seed(CompanySeeder::class);

    $this->assertDatabaseHas('companies', ['name' => 'Acme Corporation']);
    $this->assertDatabaseHas('companies', ['name' => 'TechVentures Inc.']);
    $this->assertDatabaseHas('companies', ['name' => 'Global Solutions Ltd.']);
    $this->assertDatabaseHas('companies', ['name' => 'StartUp Hub']);
    $this->assertDatabaseHas('companies', ['name' => 'Legacy Enterprises']);

    // Ensure that inactive companies exist
    $this->assertDatabaseHas('companies', [
        'name' => 'Legacy Enterprises',
        'is_active' => false,
    ]);
});
