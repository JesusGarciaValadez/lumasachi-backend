<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('a fresh central database contains only the registry and central authentication schema', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $centralConnectionName = 'central_schema_test';
    $originalCentralConnection = config('tenancy.database.central_connection');

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        [$centralDatabaseName] = $disposableDatabases->createPair();
        $centralConfiguration = (new ConfigurationUrlParser)->parseConfiguration(DB::connection()->getConfig());
        unset($centralConfiguration['url']);
        $centralConfiguration['database'] = $centralDatabaseName;
        $centralConfiguration['name'] = $centralConnectionName;
        config([
            'database.connections.' . $centralConnectionName => $centralConfiguration,
            'tenancy.database.central_connection' => $centralConnectionName,
        ]);

        $migrationExitCode = Artisan::call('tenancy:migrate-central');

        if ($migrationExitCode !== 0) {
            throw new RuntimeException('The central migration command failed: ' . Artisan::output());
        }

        $centralConnection = DB::connection($centralConnectionName);

        if (!$centralConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build a central schema test connection.');
        }

        $schema = $centralConnection->getSchemaBuilder();
        $tenantForeignKeyCount = $centralConnection->table('pg_constraint')
            ->whereRaw('conrelid = ?::regclass', ['public.tenants'])
            ->where('contype', 'f')
            ->count();
        $domainForeignKeyCount = $centralConnection->table('pg_constraint')
            ->whereRaw('conrelid = ?::regclass', ['public.domains'])
            ->where('contype', 'f')
            ->count();

        expect($schema->hasTable('companies'))->toBeTrue()
            ->and($schema->hasTable('tenants'))->toBeTrue()
            ->and($schema->hasTable('domains'))->toBeTrue()
            ->and($schema->hasTable('central_super_administrators'))->toBeTrue()
            ->and($schema->hasTable('central_password_reset_tokens'))->toBeTrue()
            ->and($schema->hasTable('sessions'))->toBeTrue()
            ->and($schema->hasTable('orders'))->toBeFalse()
            ->and($schema->hasTable('users'))->toBeFalse()
            ->and($tenantForeignKeyCount)->toBe(1)
            ->and($domainForeignKeyCount)->toBe(1)
            ->and(collect($schema->getIndexes('companies'))->contains(
                static fn(array $index): bool => $index['unique'] && $index['columns'] === ['uuid'],
            ))->toBeTrue()
            ->and(collect($schema->getIndexes('domains'))->contains(
                static fn(array $index): bool => $index['unique'] && $index['columns'] === ['domain'],
            ))->toBeTrue()
            ->and($centralConnection->selectOne('SELECT current_database() AS database_name')->database_name)
            ->toBe($centralDatabaseName);
    } finally {
        DB::purge($centralConnectionName);
        config(['tenancy.database.central_connection' => $originalCentralConnection]);
        $disposableDatabases->cleanup();
    }
});
