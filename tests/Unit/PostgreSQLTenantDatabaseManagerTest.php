<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Services\Tenancy\PostgreSQLTenantDatabaseManager;
use Illuminate\Support\Facades\DB;

test('it preserves the template host while forcing the generated tenant database and credentials', function (): void {
    $manager = new PostgreSQLTenantDatabaseManager;
    $manager->setConnection('pgsql');

    $validDatabaseName = 'lumasachi_tenant_11111111-2222-4322-8222-1234567890ab';
    $tenantUsername = 'TenantUser123456';
    $tenantPassword = 'tenant-feature-test-password';
    $baseConfig = [
        'driver' => 'pgsql',
        'url' => 'postgres://central_user:central-password@database.example.test:5433/central?sslmode=require',
        'username' => $tenantUsername,
        'password' => $tenantPassword,
    ];
    $tenantConfig = $manager->makeConnectionConfig($baseConfig, $validDatabaseName);

    expect($tenantConfig['host'])->toBe('database.example.test')
        ->and($tenantConfig['database'])->toBe($validDatabaseName)
        ->and($tenantConfig['username'])->toBe($tenantUsername)
        ->and($tenantConfig['password'])->toBe($tenantPassword)
        ->and(array_key_exists('url', $tenantConfig))->toBeFalse();

    expect(fn() => $manager->makeConnectionConfig($baseConfig, $validDatabaseName . '; DROP DATABASE postgres'))
        ->toThrow(InvalidArgumentException::class);
});

test('it refuses tenant connection configurations without tenant-specific credentials', function (): void {
    $manager = new PostgreSQLTenantDatabaseManager;
    $manager->setConnection('pgsql');

    expect(fn() => $manager->makeConnectionConfig([
        'driver' => 'pgsql',
        'username' => '',
        'password' => '',
    ], 'lumasachi_tenant_11111111-2222-4322-8222-1234567890ab'))
        ->toThrow(InvalidArgumentException::class);
});

test('it refuses a tenant connection URL that changes the database driver', function (): void {
    $manager = new PostgreSQLTenantDatabaseManager;
    $manager->setConnection('pgsql');

    expect(fn() => $manager->makeConnectionConfig([
        'driver' => 'pgsql',
        'url' => 'mysql://tenant_user:tenant-password@database.example.test/tenant',
        'username' => 'TenantUser123456',
        'password' => 'tenant-password',
    ], 'lumasachi_tenant_11111111-2222-4322-8222-1234567890ab'))
        ->toThrow(LogicException::class, 'must use PostgreSQL');
});

test('it rejects a superuser provisioner role before database operations', function (): void {
    $connectionName = 'tenant_test_superuser_provisioner';
    $originalConnection = config('database.connections.' . $connectionName);
    $originalProvisionerName = config('tenancy.database.provisioner_connection');
    $configuration = config('database.connections.' . config('database.default'));
    $configuration['name'] = $connectionName;

    config([
        'database.connections.' . $connectionName => $configuration,
        'tenancy.database.provisioner_connection' => $connectionName,
    ]);
    DB::purge($connectionName);

    try {
        $currentRoleIsSuperuser = DB::connection($connectionName)->selectOne(
            'SELECT rolsuper FROM pg_roles WHERE rolname = current_user'
        )?->rolsuper;

        expect($currentRoleIsSuperuser)->toBeTrue();

        expect(fn() => (new PostgreSQLTenantDatabaseManager)->databaseExists(
            'lumasachi_tenant_11111111-2222-4322-8222-1234567890ab'
        ))->toThrow(LogicException::class, 'without SUPERUSER');
    } finally {
        DB::purge($connectionName);
        config([
            'database.connections.' . $connectionName => $originalConnection,
            'tenancy.database.provisioner_connection' => $originalProvisionerName,
        ]);
    }
});

test('it requires a configured PostgreSQL template connection', function (): void {
    $manager = new PostgreSQLTenantDatabaseManager;

    expect(fn() => $manager->setConnection('tenant'))
        ->toThrow(LogicException::class);
});

test('it rejects a tenant database name that collides with a central database URL', function (): void {
    $centralConnectionName = 'tenant_database_url_collision_test';
    $originalCentralConnectionName = config('tenancy.database.central_connection');
    $originalCentralConfiguration = config('database.connections.' . $centralConnectionName);
    $tenant = Tenant::factory()->make(['id' => '11111111-2222-4322-8222-1234567890ab']);
    $tenant->database()->makeCredentials();
    $tenantDatabaseName = $tenant->database()->getName();

    if (!is_string($tenantDatabaseName)) {
        throw new RuntimeException('The tenancy package did not generate a tenant database name.');
    }

    config([
        'database.connections.' . $centralConnectionName => [
            'driver' => 'pgsql',
            'url' => 'postgres://central-user:central-password@database.example.test/' . $tenantDatabaseName,
            'database' => 'central-database-from-config',
            'username' => 'central-user',
            'password' => 'central-password',
        ],
        'tenancy.database.central_connection' => $centralConnectionName,
    ]);

    try {
        expect(fn() => $tenant->database()->manager()->createDatabase($tenant))
            ->toThrow(InvalidArgumentException::class, 'derived from the immutable tenant identifier');
    } finally {
        config([
            'database.connections.' . $centralConnectionName => $originalCentralConfiguration,
            'tenancy.database.central_connection' => $originalCentralConnectionName,
        ]);
    }
});
