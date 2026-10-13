<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Tenant;
use App\Services\Tenancy\TenantCacheLock;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Tenancy\DisposablePostgresDatabases;
use Tests\Support\Tenancy\TenantWorkerContextProbeJob;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('queue worker switches tenant contexts and clears them after a failed job', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $centralConnectionName = 'tenant_worker_context_central_test';
    $originalDefaultConnection = (string)config('database.default');
    $temporaryTenantStorageRoot = sys_get_temp_dir() . '/lumasachi-tenant-worker-' . bin2hex(random_bytes(8));
    $tenantCacheIsolationKey = 'tenant-worker-cache-isolation-' . bin2hex(random_bytes(8));
    $tenantStorageProbePath = 'tenant-isolation-probe.txt';
    $configurationKeys = [
        'database.default',
        'database.connections.' . $originalDefaultConnection,
        'database.connections.tenant',
        'database.connections.' . $centralConnectionName,
        'queue.connections.database.connection',
        'queue.batching.database',
        'queue.failed.database',
        'cache.default',
        'tenancy.database.central_connection',
        'tenancy.database.template_tenant_connection',
        'tenancy.filesystem.root_override.tenant-private',
    ];
    $originalConfiguration = [];
    $firstTenantLock = null;
    $secondTenantLock = null;
    $firstTenantStoragePath = null;
    $secondTenantStoragePath = null;

    foreach ($configurationKeys as $key) {
        $originalConfiguration[$key] = config($key);
    }
    try {
        $testDatabaseConfiguration = $originalConfiguration['database.connections.' . $originalDefaultConnection];

        if (!is_array($testDatabaseConfiguration) || ($testDatabaseConfiguration['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('The tenant worker context test requires the configured PostgreSQL test connection.');
        }

        unset($testDatabaseConfiguration['url']);
        $testDatabaseConfiguration['database'] = 'testing';
        config(['database.connections.' . $originalDefaultConnection => $testDatabaseConfiguration]);
        DB::purge($originalDefaultConnection);
        DB::setDefaultConnection($originalDefaultConnection);

        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        [$centralDatabaseName] = $disposableDatabases->createPair();
        $centralConfiguration = (new ConfigurationUrlParser)->parseConfiguration(DB::connection()->getConfig());
        unset($centralConfiguration['url']);
        $centralConfiguration['database'] = $centralDatabaseName;
        $centralConfiguration['name'] = $centralConnectionName;

        config([
            'database.connections.' . $centralConnectionName => $centralConfiguration,
            'queue.connections.database.connection' => $centralConnectionName,
            'queue.batching.database' => $centralConnectionName,
            'queue.failed.database' => $centralConnectionName,
            'cache.default' => 'redis',
            'tenancy.database.central_connection' => $centralConnectionName,
            'tenancy.database.template_tenant_connection' => config('database.default'),
            'tenancy.filesystem.root_override.tenant-private' => $temporaryTenantStorageRoot . '/tenant-%tenant%',
        ]);

        DB::purge($centralConnectionName);

        if (Artisan::call('tenancy:migrate-central', ['--force' => true]) !== 0) {
            throw new RuntimeException('The tenant worker test central migrations failed: ' . Artisan::output());
        }
        DB::setDefaultConnection($originalDefaultConnection);

        $tenants = [];

        foreach (['first', 'second'] as $tenantName) {
            $company = Company::query()->create(['name' => ucfirst($tenantName) . ' Worker Test']);
            $tenant = Tenant::factory()->make(['id' => $company->uuid]);
            $tenant->database()->makeCredentials();
            $disposableDatabases->registerTenantProvisioningAttempt($tenant);
            $tenant->database()->manager()->createDatabase($tenant);
            DB::setDefaultConnection($originalDefaultConnection);
            $disposableDatabases->registerProvisionedTenant($tenant);
            $tenant->save();
            $tenants[$tenantName] = $tenant;
        }

        $tenantCacheLock = app(TenantCacheLock::class);
        tenancy()->initialize($tenants['first']);

        try {
            $firstTenantLock = $tenantCacheLock->lock('same-operation', 60);
            expect($firstTenantLock->get())->toBeTrue();

            Cache::put($tenantCacheIsolationKey, 'first-tenant', now()->addMinute());
            expect(Storage::disk('tenant-private')->put($tenantStorageProbePath, 'first-tenant'))->toBeTrue();
            $firstTenantStoragePath = Storage::disk('tenant-private')->path($tenantStorageProbePath);
        } finally {
            tenancy()->end();
        }

        tenancy()->initialize($tenants['second']);

        try {
            $secondTenantLock = $tenantCacheLock->lock('same-operation', 60);
            expect($secondTenantLock->get())->toBeTrue();

            expect(Cache::get($tenantCacheIsolationKey))->toBeNull()
                ->and(Storage::disk('tenant-private')->exists($tenantStorageProbePath))->toBeFalse();

            Cache::put($tenantCacheIsolationKey, 'second-tenant', now()->addMinute());
            expect(Storage::disk('tenant-private')->put($tenantStorageProbePath, 'second-tenant'))->toBeTrue();
            $secondTenantStoragePath = Storage::disk('tenant-private')->path($tenantStorageProbePath);
        } finally {
            tenancy()->end();
        }

        tenancy()->initialize($tenants['first']);

        try {
            expect(Cache::get($tenantCacheIsolationKey))->toBe('first-tenant')
                ->and(Storage::disk('tenant-private')->get($tenantStorageProbePath))->toBe('first-tenant')
                ->and(Storage::disk('tenant-private')->path($tenantStorageProbePath))->toBe($firstTenantStoragePath)
                ->and($firstTenantStoragePath)->not->toBe($secondTenantStoragePath);
        } finally {
            tenancy()->end();
        }

        tenancy()->initialize($tenants['second']);

        try {
            expect(Cache::get($tenantCacheIsolationKey))->toBe('second-tenant')
                ->and(Storage::disk('tenant-private')->get($tenantStorageProbePath))->toBe('second-tenant')
                ->and(Storage::disk('tenant-private')->path($tenantStorageProbePath))->toBe($secondTenantStoragePath);
        } finally {
            tenancy()->end();
        }

        TenantWorkerContextProbeJob::$observations = [];

        foreach ([
                     ['first-before-switch', $tenants['first'], false],
                     ['second-after-switch', $tenants['second'], false],
                     ['first-throws', $tenants['first'], true],
                     ['second-after-failure', $tenants['second'], false],
                 ] as [$label, $tenant, $shouldThrow]) {
            tenancy()->initialize($tenant);

            try {
                Queue::connection('database')->push(new TenantWorkerContextProbeJob($label, $shouldThrow));
            } finally {
                tenancy()->end();
            }
        }

        $workerExitCode = Artisan::call('queue:work', [
            'connection' => 'database',
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--sleep' => 0,
            '--timeout' => 10,
            '--memory' => 0,
        ]);
        $firstTenantDatabase = $tenants['first']->database()->getName();
        $secondTenantDatabase = $tenants['second']->database()->getName();
        $failedJob = DB::connection($centralConnectionName)->table('failed_jobs')->first();

        expect($workerExitCode)->toBe(0, Artisan::output())
            ->and(TenantWorkerContextProbeJob::$observations)->toBe([
                [
                    'label' => 'first-before-switch',
                    'tenant_id' => $tenants['first']->getTenantKey(),
                    'database' => $firstTenantDatabase,
                    'cache_value' => $tenants['first']->getTenantKey(),
                ],
                [
                    'label' => 'second-after-switch',
                    'tenant_id' => $tenants['second']->getTenantKey(),
                    'database' => $secondTenantDatabase,
                    'cache_value' => $tenants['second']->getTenantKey(),
                ],
                [
                    'label' => 'first-throws',
                    'tenant_id' => $tenants['first']->getTenantKey(),
                    'database' => $firstTenantDatabase,
                    'cache_value' => $tenants['first']->getTenantKey(),
                ],
                [
                    'label' => 'second-after-failure',
                    'tenant_id' => $tenants['second']->getTenantKey(),
                    'database' => $secondTenantDatabase,
                    'cache_value' => $tenants['second']->getTenantKey(),
                ],
            ])
            ->and($failedJob)->not->toBeNull()
            ->and($failedJob->exception)->toContain('Simulated tenant queue job failure.')
            ->and(tenancy()->initialized)->toBeFalse();
    } finally {
        $secondTenantLock?->release();
        $firstTenantLock?->release();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::purge('tenant');
        DB::purge($centralConnectionName);
        DB::setDefaultConnection($originalDefaultConnection);

        try {
            $disposableDatabases->cleanup();
        } finally {
            config($originalConfiguration);
            DB::purge($centralConnectionName);
            Storage::forgetDisk('tenant-private');
            File::deleteDirectory($temporaryTenantStorageRoot);
        }
    }
});
