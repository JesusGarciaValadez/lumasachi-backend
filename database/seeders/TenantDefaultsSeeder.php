<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Seeds tenant reference data without creating operational records. */
final class TenantDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ServiceCatalogSeeder::class);
    }
}
