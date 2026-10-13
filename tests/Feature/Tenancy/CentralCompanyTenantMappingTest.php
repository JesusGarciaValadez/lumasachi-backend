<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('a central company and its tenant resolve through one immutable uuid and unique host', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $centralConnectionName = 'central_company_tenant_mapping_test';
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

        $company = Company::query()->create(['name' => 'Example Company']);
        $tenant = Tenant::query()->create(['id' => $company->uuid]);
        $tenant->domains()->create(['domain' => 'ALPHA.Example.test']);

        $resolvedCompany = Tenant::query()
            ->with('company')
            ->whereKey($company->uuid)
            ->firstOrFail();
        $resolvedTenant = Company::query()
            ->with('tenant')
            ->whereKey($company->id)
            ->firstOrFail();
        $resolvedDomain = Domain::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->firstOrFail();

        expect($resolvedCompany->company?->is($company))->toBeTrue()
            ->and($resolvedTenant->tenant?->is($tenant))->toBeTrue()
            ->and($resolvedCompany->getTenantKey())->toBe($company->uuid)
            ->and($resolvedDomain->domain)->toBe('alpha.example.test');
    } finally {
        DB::purge($centralConnectionName);
        config([
            'database.connections.' . $centralConnectionName => $originalCentralConnectionConfiguration,
            'tenancy.database.central_connection' => $originalCentralConnectionName,
        ]);
        $disposableDatabases->cleanup();
    }
});
