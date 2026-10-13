<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Models\Tenant;
use Database\Seeders\TenantDefaultsSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('tenant defaults synchronize the catalog only and preserve order price snapshots', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
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

            $connectionName = 'tenant_reference_' . $tenant->getTenantKey();
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

            $seederExitCode = Artisan::call('db:seed', [
                '--class' => TenantDefaultsSeeder::class,
                '--database' => $connectionName,
                '--force' => true,
                '--no-interaction' => true,
            ]);

            if ($seederExitCode !== 0) {
                throw new RuntimeException('The tenant defaults seeder failed: ' . Artisan::output());
            }
        }

        [$firstConnectionName, $secondConnectionName] = $connectionNames;
        $firstConnection = DB::connection($firstConnectionName);
        $secondConnection = DB::connection($secondConnectionName);
        $catalogColumns = [
            'service_key',
            'service_name_key',
            'item_type',
            'base_price',
            'tax_percentage',
            'requires_measurement',
            'is_active',
            'display_order',
        ];
        $readCatalog = static fn(Connection $connection): array => $connection->table('service_catalog')
            ->orderBy('service_key')
            ->get($catalogColumns)
            ->map(static fn(object $row): array => (array)$row)
            ->all();
        $firstCatalog = $readCatalog($firstConnection);
        $secondCatalog = $readCatalog($secondConnection);

        $operationalTables = [
            'users',
            'orders',
            'order_motor_info',
            'order_items',
            'order_item_components',
            'order_services',
            'order_histories',
            'order_payments',
            'order_refunds',
            'attachments',
        ];
        $firstOperationalCounts = collect($operationalTables)
            ->mapWithKeys(static fn(string $table): array => [$table => $firstConnection->table($table)->count()])
            ->all();
        $secondOperationalCounts = collect($operationalTables)
            ->mapWithKeys(static fn(string $table): array => [$table => $secondConnection->table($table)->count()])
            ->all();

        expect($firstCatalog)->not->toBeEmpty()
            ->and($firstCatalog)->toBe($secondCatalog)
            ->and($firstOperationalCounts)->toBe(array_fill_keys($operationalTables, 0))
            ->and($secondOperationalCounts)->toBe(array_fill_keys($operationalTables, 0));

        $baselineService = $firstConnection->table('service_catalog')
            ->where('service_key', 'wash_block')
            ->first();
        $catalogCount = $firstConnection->table('service_catalog')->count();

        if ($baselineService === null) {
            throw new RuntimeException('The tenant catalog is missing its wash_block default.');
        }

        $userId = $firstConnection->table('users')->insertGetId([
            'uuid' => (string)Str::uuid7(),
            'first_name' => 'Snapshot',
            'last_name' => 'Test',
            'email' => 'snapshot-test@example.test',
            'password' => 'not-used-by-this-test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderId = $firstConnection->table('orders')->insertGetId([
            'uuid' => (string)Str::uuid7(),
            'customer_id' => $userId,
            'assigned_to' => $userId,
            'title' => 'Price snapshot preservation test',
            'description' => 'Test-only order for catalog synchronization.',
            'lifecycle_status' => OrderLifecycleStatus::AwaitingReview->value,
            'priority' => OrderPriority::NORMAL->value,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderItemId = $firstConnection->table('order_items')->insertGetId([
            'uuid' => (string)Str::uuid7(),
            'order_id' => $orderId,
            'item_type' => OrderItemType::EngineBlock->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $firstConnection->table('order_services')->insert([
            'uuid' => (string)Str::uuid7(),
            'order_item_id' => $orderItemId,
            'service_key' => 'wash_block',
            'is_budgeted' => true,
            'base_price' => $baselineService->base_price,
            'net_price' => '696.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstConnection->table('service_catalog')
            ->where('service_key', 'wash_block')
            ->update(['base_price' => '999.99', 'tax_percentage' => '0.00']);

        $seederExitCode = Artisan::call('db:seed', [
            '--class' => TenantDefaultsSeeder::class,
            '--database' => $firstConnectionName,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($seederExitCode !== 0) {
            throw new RuntimeException('The tenant defaults resynchronization failed: ' . Artisan::output());
        }

        $synchronizedService = $firstConnection->table('service_catalog')
            ->where('service_key', 'wash_block')
            ->first();
        $secondCompanyService = $secondConnection->table('service_catalog')
            ->where('service_key', 'wash_block')
            ->first();
        $orderServiceSnapshot = $firstConnection->table('order_services')
            ->where('order_item_id', $orderItemId)
            ->first(['base_price', 'net_price']);

        expect($synchronizedService?->base_price)->toBe($baselineService->base_price)
            ->and($synchronizedService?->tax_percentage)->toBe($baselineService->tax_percentage)
            ->and($firstConnection->table('service_catalog')->count())->toBe($catalogCount)
            ->and($readCatalog($firstConnection))->toBe($readCatalog($secondConnection))
            ->and($secondCompanyService?->base_price)->toBe($baselineService->base_price)
            ->and($orderServiceSnapshot?->base_price)->toBe($baselineService->base_price)
            ->and($orderServiceSnapshot?->net_price)->toBe('696.00')
            ->and($firstConnection->table('orders')->count())->toBe(1)
            ->and($secondConnection->table('orders')->count())->toBe(0)
            ->and($secondConnection->table('order_services')->count())->toBe(0);
    } finally {
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
