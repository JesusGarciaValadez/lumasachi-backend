<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../../Support/Tenancy/DisposablePostgresDatabases.php';

it('creates and manages companies in an isolated central registry database', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases();
    $centralConnectionName = 'central_company_model_test';
    $originalCentralConnectionName = config('tenancy.database.central_connection');
    $originalCentralConnectionConfiguration = config('database.connections.' . $centralConnectionName);

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        [$centralDatabaseName] = $disposableDatabases->createPair();
        $centralConfiguration = (new ConfigurationUrlParser())->parseConfiguration(DB::connection()->getConfig());
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

        $activeCompany = Company::factory()->active()->create(['name' => 'Central Active Company']);
        Company::factory()->inactive()->create(['name' => 'Central Inactive Company']);
        $originalCompanyUuid = $activeCompany->uuid;

        expect(Company::active()->pluck('id')->all())->toBe([$activeCompany->id])
            ->and(Company::inactive()->count())->toBe(1);

        $activeCompany->uuid = (string)Str::uuid7();
        expect(fn(): bool => $activeCompany->save())
            ->toThrow(LogicException::class, 'A company UUID cannot be changed after creation.');
        $activeCompany->refresh();

        $activeCompany->update(['name' => 'Updated Central Company']);
        expect(Company::query()->findOrFail($activeCompany->id)->name)->toBe('Updated Central Company');

        $activeCompany->delete();
        expect(Company::query()->find($activeCompany->id))->toBeNull();
    } finally {
        DB::purge($centralConnectionName);
        config([
            'database.connections.' . $centralConnectionName => $originalCentralConnectionConfiguration,
            'tenancy.database.central_connection' => $originalCentralConnectionName,
        ]);
        $disposableDatabases->cleanup();
    }
});
