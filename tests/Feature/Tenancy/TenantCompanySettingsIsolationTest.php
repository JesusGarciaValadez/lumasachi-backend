<?php

declare(strict_types=1);

use App\Models\CompanySetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('company settings are stored only in their initialized tenant database', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases();
    $connectionNames = [];
    $tenants = [];

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();

        for ($index = 0; $index < 2; $index++) {
            $tenant = Tenant::factory()->make();
            $tenant->database()->makeCredentials();
            $disposableDatabases->registerTenantProvisioningAttempt($tenant);
            $tenant->database()->manager()->createDatabase($tenant);
            $tenants[] = $tenant;
            $disposableDatabases->registerProvisionedTenant($tenant);

            $connectionName = 'tenant_company_settings_' . $tenant->getTenantKey();
            $connectionNames[] = $connectionName;
            config([
                'database.connections.' . $connectionName => $tenant->database()->connection(),
            ]);

            $migrationExitCode = Artisan::call('migrate', [
                '--database' => $connectionName,
                '--force' => true,
                '--no-interaction' => true,
                '--path' => [database_path('migrations/tenant')],
                '--realpath' => true,
            ]);

            if ($migrationExitCode !== 0) {
                throw new RuntimeException('The tenant migration command failed: ' . Artisan::output());
            }
        }

        tenancy()->initialize($tenants[0]);
        try {
            CompanySetting::query()->create([
                'name' => 'First Company Settings',
                'email' => 'first@example.test',
                'settings' => ['timezone' => 'America/Mexico_City'],
            ]);
        } finally {
            tenancy()->end();
        }

        tenancy()->initialize($tenants[1]);
        try {
            expect(CompanySetting::query()->count())->toBe(0);
        } finally {
            tenancy()->end();
        }

        [$firstConnectionName, $secondConnectionName] = $connectionNames;

        expect(DB::connection($firstConnectionName)->table('company_settings')->value('name'))
            ->toBe('First Company Settings')
            ->and(DB::connection($secondConnectionName)->table('company_settings')->count())->toBe(0)
            ->and(DB::connection((string)config('tenancy.database.central_connection'))
                ->getSchemaBuilder()
                ->hasTable('company_settings'))->toBeFalse();
    } finally {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($connectionNames as $connectionName) {
            DB::purge($connectionName);
        }

        try {
            foreach ($tenants as $tenant) {
                $tenant->database()->manager()->deleteDatabase($tenant);
            }
        } finally {
            $disposableDatabases->cleanup();
        }
    }
});
