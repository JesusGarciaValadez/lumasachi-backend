<?php

declare(strict_types=1);

use App\Models\CentralSuperAdministrator;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('the central super administrator factory persists against the configured central schema', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $centralConnectionName = 'central_super_administrator_test';
    $originalCentralConnectionName = config('tenancy.database.central_connection');
    $originalCentralConnectionConfiguration = config('database.connections.' . $centralConnectionName);

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

        $centralSuperAdministrator = CentralSuperAdministrator::factory()->create();
        $reloadedCentralSuperAdministrator = CentralSuperAdministrator::query()
            ->where('uuid', $centralSuperAdministrator->uuid)
            ->firstOrFail();

        expect($centralSuperAdministrator->getConnectionName())
            ->toBe(config('tenancy.database.central_connection'))
            ->and($centralSuperAdministrator->getTable())
            ->toBe('central_super_administrators')
            ->and($reloadedCentralSuperAdministrator->is($centralSuperAdministrator))
            ->toBeTrue()
            ->and(Hash::check('password', $reloadedCentralSuperAdministrator->password))
            ->toBeTrue()
            ->and($reloadedCentralSuperAdministrator->must_change_password)
            ->toBeFalse()
            ->and($reloadedCentralSuperAdministrator->is_active)
            ->toBeTrue()
            ->and($reloadedCentralSuperAdministrator->email_verified_at)
            ->toBeInstanceOf(DateTimeInterface::class)
            ->and($reloadedCentralSuperAdministrator->activated_at)
            ->toBeInstanceOf(DateTimeInterface::class)
            ->and($reloadedCentralSuperAdministrator->toArray())
            ->not->toHaveKeys(['password', 'remember_token']);
    } finally {
        DB::purge($centralConnectionName);
        config([
            'database.connections.' . $centralConnectionName => $originalCentralConnectionConfiguration,
            'tenancy.database.central_connection' => $originalCentralConnectionName,
        ]);
        $disposableDatabases->cleanup();
    }
});
