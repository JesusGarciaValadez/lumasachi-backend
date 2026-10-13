<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('a fresh tenant database receives the complete tenant-only schema', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenant = Tenant::factory()->make();
    $connectionName = 'tenant_migration_' . $tenant->getTenantKey();

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();

        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);
        $tenant->database()->manager()->createDatabase($tenant);
        $disposableDatabases->registerProvisionedTenant($tenant);

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

        $tenantConnection = DB::connection($connectionName);
        $schema = $tenantConnection->getSchemaBuilder();

        expect($schema->hasTable('users'))->toBeTrue()
            ->and($schema->hasTable('orders'))->toBeTrue()
            ->and($schema->hasTable('company_settings'))->toBeTrue()
            ->and($schema->hasTable('order_items'))->toBeTrue()
            ->and($schema->hasTable('order_item_components'))->toBeTrue()
            ->and($schema->hasTable('order_services'))->toBeTrue()
            ->and($schema->hasTable('order_histories'))->toBeTrue()
            ->and($schema->hasTable('order_payments'))->toBeTrue()
            ->and($schema->hasTable('order_refunds'))->toBeTrue()
            ->and($schema->hasTable('attachments'))->toBeTrue()
            ->and($schema->hasTable('companies'))->toBeFalse()
            ->and($schema->hasColumn('users', 'company_id'))->toBeFalse()
            ->and($schema->hasColumn('order_services', 'updated_by'))->toBeFalse();

        expect($schema->hasColumn('company_settings', 'locale'))->toBeTrue()
            ->and($schema->hasColumn('company_settings', 'email_from_address'))->toBeTrue()
            ->and($schema->hasColumn('company_settings', 'settings'))->toBeTrue();

        $customerIdNullability = $tenantConnection->table('information_schema.columns')
            ->where('table_name', 'orders')
            ->where('column_name', 'customer_id')
            ->value('is_nullable');

        expect($customerIdNullability)->toBe('NO')
            ->and(collect($schema->getIndexes('orders'))->contains(
                static fn(array $index): bool => $index['unique'] && $index['columns'] === ['uuid'],
            ))->toBeTrue();

        expect(fn() => $tenantConnection->table('users')->insert([
            'uuid' => (string)Str::uuid(),
            'first_name' => 'Central',
            'last_name' => 'Administrator',
            'email' => 'central-admin@example.test',
            'password' => 'not-a-real-password',
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'is_active' => true,
        ]))->toThrow(QueryException::class);
    } finally {
        DB::purge($connectionName);
        $disposableDatabases->cleanup();
    }
});
