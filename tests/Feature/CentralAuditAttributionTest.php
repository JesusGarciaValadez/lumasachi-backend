<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\CentralSuperAdministrator;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\UserAdministrationIncidentNotification;
use App\Services\Tenancy\TenantSupportActorService;
use App\Services\UserAdministrationIncidentReporter;
use Illuminate\Http\Request;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../Support/Tenancy/DisposablePostgresDatabases.php';

test('central super administrator order history uses a non-login local support actor and central uuid', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $centralConnectionName = 'central_audit_attribution_test';
    $tenantConnectionName = 'tenant_audit_attribution_test';
    $originalDefaultConnectionName = config('database.default');
    $originalCentralConnectionName = config('tenancy.database.central_connection');
    $originalCentralConnectionConfiguration = config('database.connections.' . $centralConnectionName);
    $originalTenantConnectionConfiguration = config('database.connections.' . $tenantConnectionName);
    $originalCentralDomains = config('tenancy.central_domains');
    $originalCentralHosts = config('tenancy.central_hosts');

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
            'tenancy.central_domains' => ['example.test'],
            'tenancy.central_hosts' => ['app.example.test'],
            'tenancy.allow_legacy_model_connection' => false,
        ]);

        $centralMigrationExitCode = Artisan::call('tenancy:migrate-central');

        if ($centralMigrationExitCode !== 0) {
            throw new RuntimeException('The central migration command failed: ' . Artisan::output());
        }

        config(['database.default' => $originalDefaultConnectionName]);
        DB::setDefaultConnection($originalDefaultConnectionName);

        $centralAdministrator = CentralSuperAdministrator::factory()->create();
        $company = Company::query()->create(['name' => 'Audit Attribution Company']);
        $tenant = Tenant::factory()->make(['id' => $company->uuid]);
        $tenant->save();
        $tenant->domains()->create(['domain' => 'audit-attribution.example.test']);
        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);
        $tenant->database()->manager()->createDatabase($tenant);
        $disposableDatabases->registerProvisionedTenant($tenant);

        config(['database.connections.' . $tenantConnectionName => $tenant->database()->connection()]);

        $tenantMigrationExitCode = Artisan::call('migrate', [
            '--database' => $tenantConnectionName,
            '--force' => true,
            '--no-interaction' => true,
            '--path' => [database_path('migrations/tenant')],
            '--realpath' => true,
        ]);

        if ($tenantMigrationExitCode !== 0) {
            throw new RuntimeException('The tenant migration command failed: ' . Artisan::output());
        }

        tenancy()->initialize($tenant);

        $supportActors = app(TenantSupportActorService::class);
        $supportActor = $supportActors->for($centralAdministrator);
        $retriedSupportActor = $supportActors->for($centralAdministrator);
        $historyForeignKeys = Schema::connection('tenant')->getForeignKeys('order_histories');

        expect($supportActor->isPlatformSupport())
            ->toBeTrue()
            ->and($supportActor->is_active)
            ->toBeFalse()
            ->and($supportActor->role)
            ->toBe(UserRole::ADMINISTRATOR)
            ->and($supportActor->central_actor_uuid)
            ->toBe($centralAdministrator->uuid)
            ->and($retriedSupportActor->is($supportActor))
            ->toBeTrue()
            ->and($supportActor->toArray())
            ->not->toHaveKeys(['is_platform_support', 'central_actor_uuid'])
            ->and(collect($historyForeignKeys)->contains(
                static fn(array $foreignKey): bool => in_array('central_actor_uuid', $foreignKey['columns'], true),
            ))
            ->toBeFalse();

        Auth::forgetGuards();
        expect(Auth::guard('web')->attempt([
            'email' => $supportActor->email,
            'password' => 'not-the-generated-password',
            'is_active' => true,
        ]))->toBeFalse();

        Auth::forgetGuards();
        Auth::guard('web')->setUser($supportActor);
        $regularUser = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $order = Order::factory()->createQuietly([
            'created_by' => $regularUser->id,
            'updated_by' => $regularUser->id,
            'assigned_to' => $regularUser->id,
        ]);

        $history = OrderHistory::query()->create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_NOTES,
            'old_value' => null,
            'new_value' => 'Central support update',
            'created_by' => $regularUser->id,
        ]);
        $reloadedHistory = OrderHistory::query()->findOrFail($history->id);

        Notification::fake();
        $tenantAdministrator = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $incidentRequest = Request::create('/user/' . $tenantAdministrator->uuid, 'PUT');
        $incidentRequest->setUserResolver(static fn(): User => $tenantAdministrator);
        $incident = app(UserAdministrationIncidentReporter::class)->capture(
            new RuntimeException('Unexpected test failure.'),
            $incidentRequest,
            'update',
        );

        expect($reloadedHistory->created_by)
            ->toBe($supportActor->id)
            ->and($reloadedHistory->central_actor_uuid)
            ->toBe($centralAdministrator->uuid)
            ->and($reloadedHistory->toArray())
            ->not->toHaveKey('central_actor_uuid');

        Notification::assertSentTo($centralAdministrator, UserAdministrationIncidentNotification::class);
        Notification::assertNotSentTo($tenantAdministrator, UserAdministrationIncidentNotification::class);
    } finally {
        Auth::forgetGuards();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::purge($tenantConnectionName);
        DB::purge('tenant');
        config([
            'database.default' => $originalDefaultConnectionName,
            'database.connections.' . $tenantConnectionName => $originalTenantConnectionConfiguration,
            'database.connections.' . $centralConnectionName => $originalCentralConnectionConfiguration,
            'tenancy.database.central_connection' => $originalCentralConnectionName,
            'tenancy.central_domains' => $originalCentralDomains,
            'tenancy.central_hosts' => $originalCentralHosts,
        ]);
        DB::setDefaultConnection($originalDefaultConnectionName);
        DB::purge($centralConnectionName);
        $disposableDatabases->cleanup();
    }
});
