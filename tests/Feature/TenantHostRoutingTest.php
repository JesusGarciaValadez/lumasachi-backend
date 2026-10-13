<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Http\Middleware\RequireCentralDomain;
use App\Http\Middleware\TenantRequestContext;
use App\Jobs\Middleware\TenantNotificationContext;
use App\Models\CentralSuperAdministrator;
use App\Models\Company;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderAuditNotification;
use App\Providers\TenancyServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Stancl\Tenancy\Controllers\TenantAssetsController;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../Support/Tenancy/DisposablePostgresDatabases.php';

test('tenant hosts resolve only ready active companies and clear worker context after each request', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases();
    $centralConnectionName = 'tenant_host_routing_registry_test';
    $originalDefaultConnectionName = config('database.default');
    $originalCentralConnectionName = config('tenancy.database.central_connection');
    $originalCentralConnectionConfiguration = config('database.connections.' . $centralConnectionName);
    $originalSessionConnection = config('session.connection');
    $originalCentralDomains = config('tenancy.central_domains');
    $originalCentralHosts = config('tenancy.central_hosts');
    $tenantConnectionNames = [];
    $tenants = [];
    $tenantPrivatePath = 'tenancy-isolation-tests/' . Str::uuid() . '.txt';
    $intakeUsers = [];

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
            'tenancy.central_domains' => ['example.test'],
            'tenancy.central_hosts' => ['app.example.test'],
            'tenancy.allow_legacy_model_connection' => false,
            'tenancy.allow_legacy_test_routes' => false,
        ]);

        $migrationExitCode = Artisan::call('tenancy:migrate-central');

        if ($migrationExitCode !== 0) {
            throw new RuntimeException('The central migration command failed: ' . Artisan::output());
        }

        foreach (['alpha', 'beta'] as $subdomain) {
            $company = Company::query()->create(['name' => ucfirst($subdomain) . ' Company']);
            $tenant = Tenant::factory()->make(['id' => $company->uuid]);
            $tenant->save();
            $tenant->domains()->create(['domain' => $subdomain . '.example.test']);
            $tenant->database()->makeCredentials();
            $disposableDatabases->registerTenantProvisioningAttempt($tenant);
            $tenant->database()->manager()->createDatabase($tenant);
            $disposableDatabases->registerProvisionedTenant($tenant);

            $connectionName = 'tenant_host_routing_' . $subdomain;
            $tenantConnectionNames[] = $connectionName;
            config(['database.connections.' . $connectionName => $tenant->database()->connection()]);

            $tenantMigrationExitCode = Artisan::call('migrate', [
                '--database' => $connectionName,
                '--force' => true,
                '--no-interaction' => true,
                '--path' => [database_path('migrations/tenant')],
                '--realpath' => true,
            ]);

            if ($tenantMigrationExitCode !== 0) {
                throw new RuntimeException('The tenant migration command failed: ' . Artisan::output());
            }

            $tenant->forceFill([
                'provisioning_status' => 'ready',
                'ready_at' => now(),
            ])->save();
            $tenants[$subdomain] = $tenant;
        }

        foreach ($tenants as $subdomain => $tenant) {
            $tenant->run(function () use ($subdomain, &$intakeUsers): void {
                if ($subdomain === 'beta') {
                    User::factory()->create([
                        'role' => UserRole::EMPLOYEE->value,
                        'email' => 'beta-existing-employee@example.test',
                    ]);
                }

                $administrator = User::factory()->create([
                    'role' => UserRole::ADMINISTRATOR->value,
                    'email' => 'same-admin@example.test',
                ]);
                $customer = User::factory()->create([
                    'role' => UserRole::CUSTOMER->value,
                    'email' => "{$subdomain}-customer@example.test",
                ]);
                $employee = User::factory()->create([
                    'role' => UserRole::EMPLOYEE->value,
                    'email' => "{$subdomain}-employee@example.test",
                ]);

                $intakeUsers[$subdomain] = [
                    'administrator' => $administrator,
                    'customer' => $customer,
                    'employee' => $employee,
                    'token' => $administrator->createToken('tenant-order-intake')->plainTextToken,
                ];
            });
        }

        $inactiveCompany = Company::query()->create(['name' => 'Inactive Company', 'is_active' => false]);
        $inactiveTenant = Tenant::factory()->create([
            'id' => $inactiveCompany->uuid,
            'provisioning_status' => 'ready',
            'ready_at' => now(),
        ]);
        $inactiveTenant->domains()->create(['domain' => 'inactive.example.test']);

        $pendingCompany = Company::query()->create(['name' => 'Pending Company']);
        $pendingTenant = Tenant::factory()->create(['id' => $pendingCompany->uuid]);
        $pendingTenant->domains()->create(['domain' => 'pending.example.test']);

        Route::middleware(TenantRequestContext::class)->get('/__tenant-context', static function (): JsonResponse {
            $tenant = tenancy()->tenant;

            return response()->json([
                'tenant_id' => $tenant?->getTenantKey(),
                'database' => DB::connection('tenant')->selectOne('select current_database() as database')->database,
                'session_connection' => config('session.connection'),
            ]);
        });

        Route::middleware(RequireCentralDomain::class)->get('/__central-session-connection', static function (): JsonResponse {
            return response()->json(['session_connection' => config('session.connection')]);
        });

        Route::middleware(TenantRequestContext::class)->get('/__tenant-context/fail', static function (): never {
            DB::connection('tenant')->selectOne('select current_database()');

            throw new RuntimeException('Tenant route failed after database access.');
        });

        foreach ($tenants as $subdomain => $tenant) {
            $response = $this->get('http://' . $subdomain . '.example.test/__tenant-context')
                ->assertSuccessful()
                ->assertJsonPath('tenant_id', $tenant->getTenantKey())
                ->assertJsonPath('database', $tenant->database()->getName())
                ->assertJsonPath('session_connection', 'tenant');

            unset($response);

            expect(tenancy()->initialized)->toBeFalse()
                ->and(DomainTenantResolver::$currentDomain)->toBeNull()
                ->and(DB::getDefaultConnection())->toBe($centralConnectionName);
        }

        foreach ($tenants as $subdomain => $tenant) {
            $tenant->run(static function () use ($subdomain, $tenantPrivatePath): void {
                Storage::disk('tenant-private')->put($tenantPrivatePath, $subdomain);

                expect(Storage::disk('tenant-private')->get($tenantPrivatePath))->toBe($subdomain)
                    ->and(Storage::disk('public')->exists($tenantPrivatePath))->toBeFalse();
            });
        }

        $centralAdministrator = CentralSuperAdministrator::factory()->create();
        Notification::fake();

        foreach ($tenants as $subdomain => $tenant) {
            $customerResponse = $this->getJson('http://' . $subdomain . '.example.test/api/v1/users/customers', [
                'Authorization' => 'Bearer ' . $intakeUsers[$subdomain]['token'],
            ])->assertSuccessful();

            $customerResponse->assertJsonFragment([
                'uuid' => $intakeUsers[$subdomain]['customer']->uuid,
            ]);

            if ($subdomain === 'alpha') {
                $customerResponse->assertJsonMissing([
                    'uuid' => $intakeUsers['beta']['customer']->uuid,
                ]);
            }
        }

        $this->getJson('http://alpha.example.test/api/v1/users/customers?company_id=' . $tenants['beta']->company()->value('id'), [
            'Authorization' => 'Bearer ' . $intakeUsers['alpha']['token'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id']);

        $orderPayload = [
            'customer_id' => $intakeUsers['alpha']['customer']->id,
            'title' => 'Tenant-derived order',
            'description' => 'Company is resolved exclusively from the request host.',
            'priority' => OrderPriority::NORMAL->value,
            'assigned_to' => $intakeUsers['alpha']['employee']->id,
            'items' => [['item_type' => OrderItemType::CylinderHead->value]],
        ];

        $createdOrderResponse = $this->postJson('http://alpha.example.test/api/v1/orders', $orderPayload, [
            'Authorization' => 'Bearer ' . $intakeUsers['alpha']['token'],
        ])->assertCreated()
            ->assertJsonPath('order.customer.id', $intakeUsers['alpha']['customer']->id)
            ->assertJsonPath('order.assigned_to.id', $intakeUsers['alpha']['employee']->id);
        $createdOrderUuid = $createdOrderResponse->json('order.uuid');

        expect($createdOrderUuid)->toBeString();

        $this->getJson('http://alpha.example.test/api/v1/orders', [
            'Authorization' => 'Bearer ' . $intakeUsers['alpha']['token'],
        ])->assertSuccessful()->assertJsonFragment(['uuid' => $createdOrderUuid]);

        $this->getJson('http://beta.example.test/api/v1/orders', [
            'Authorization' => 'Bearer ' . $intakeUsers['beta']['token'],
        ])->assertSuccessful()->assertJsonMissing(['uuid' => $createdOrderUuid]);

        $this->getJson('http://beta.example.test/api/v1/orders/' . $createdOrderUuid, [
            'Authorization' => 'Bearer ' . $intakeUsers['beta']['token'],
        ])->assertNotFound();

        $this->getJson('http://alpha.example.test/api/v1/orders/' . $createdOrderUuid . '/history', [
            'Authorization' => 'Bearer ' . $intakeUsers['alpha']['token'],
        ])->assertSuccessful()
            ->assertJsonFragment(['order_id' => $createdOrderResponse->json('order.id')]);

        $this->getJson('http://beta.example.test/api/v1/orders/' . $createdOrderUuid . '/history', [
            'Authorization' => 'Bearer ' . $intakeUsers['beta']['token'],
        ])->assertNotFound();

        $this->postJson('http://alpha.example.test/api/v1/orders', [
            ...$orderPayload,
            'company_id' => $tenants['beta']->company()->value('id'),
        ], [
            'Authorization' => 'Bearer ' . $intakeUsers['alpha']['token'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['company_id']);

        Notification::assertSentTo($intakeUsers['alpha']['administrator'], OrderAuditNotification::class);
        Notification::assertSentTo($centralAdministrator, OrderAuditNotification::class);
        Notification::assertNotSentTo($intakeUsers['beta']['administrator'], OrderAuditNotification::class);

        $tenants['alpha']->run(static function (): void {
            expect(Order::query()->count())->toBe(1);
        });

        $tenants['beta']->run(static function (): void {
            expect(Order::query()->count())->toBe(0);
        });

        foreach ($tenants as $subdomain => $tenant) {
            $tenant->run(static function () use ($subdomain, $tenantPrivatePath): void {
                expect(Storage::disk('tenant-private')->get($tenantPrivatePath))->toBe($subdomain);
            });
        }

        $privateAssetResponse = $this->get('http://alpha.example.test/tenancy/assets/' . $tenantPrivatePath);

        expect($privateAssetResponse->getStatusCode())->toBeGreaterThanOrEqual(400)
            ->and(Storage::disk('public')->exists($tenantPrivatePath))->toBeFalse();

        expect(config('database.connections.' . $centralConnectionName))->toBeArray()
            ->and(config('tenancy.database.central_connection'))->toBe($centralConnectionName);

        $this->get('http://app.example.test/__tenant-context')->assertNotFound();
        $this->get('http://example.test/__tenant-context')->assertNotFound();
        expect(tenancy()->initialized)->toBeFalse()
            ->and(DB::getDefaultConnection())->toBe($centralConnectionName);
        expect(config('database.connections.' . $centralConnectionName))->toBeArray();
        $this->get('http://unknown.example.test/__tenant-context')->assertNotFound();
        expect(config('database.connections.' . $centralConnectionName))->toBeArray();
        $this->get('http://inactive.example.test/__tenant-context')->assertNotFound();
        $this->get('http://pending.example.test/__tenant-context')->assertNotFound();
        $attackerResponse = $this->get('http://attacker.invalid/__tenant-context');

        expect($attackerResponse->baseResponse->getStatusCode())->toBe(404);
        $this->get('http://app.example.test/up')->assertSuccessful();
        $this->get('http://app.example.test/__central-session-connection')
            ->assertSuccessful()
            ->assertJsonPath('session_connection', $centralConnectionName);
        expect(config('session.connection'))->toBe($originalSessionConnection);
        $this->get('http://example.test/up')->assertNotFound();
        $this->get('http://alpha.example.test/up')->assertNotFound();
        $this->get('http://app.example.test/orders/create')->assertNotFound();
        $this->get('http://app.example.test/orders/track')->assertNotFound();
        expect(TenantAssetsController::$tenancyMiddleware)->toBe(TenantRequestContext::class);
        $this->get('http://app.example.test/tenancy/assets/missing.txt')->assertNotFound();

        $orderFormResponse = $this->get('http://alpha.example.test/orders/create');

        expect($orderFormResponse->getStatusCode())->toBe(302)
            ->and($orderFormResponse->headers->get('Location'))->toContain('alpha.example.test/login');

        Exceptions::fake();
        $this->get('http://alpha.example.test/__tenant-context/fail')->assertInternalServerError();
        Exceptions::assertReported(RuntimeException::class);

        expect(tenancy()->initialized)->toBeFalse()
            ->and(DomainTenantResolver::$currentDomain)->toBeNull()
            ->and(DB::getDefaultConnection())->toBe($centralConnectionName);

        $urlGenerator = URL::getFacadeRoot();
        $originalOrigin = $urlGenerator->formatRoot($urlGenerator->formatScheme());
        $notificationContext = app(TenantNotificationContext::class);
        $tenantOrigin = $urlGenerator->formatScheme() . 'alpha.example.test';

        $tenants['alpha']->run(function () use ($notificationContext, $tenantOrigin): void {
            $notificationContext->handle(new stdClass(), function () use ($tenantOrigin): string {
                expect(URL::to('/orders/track'))->toStartWith($tenantOrigin);

                return 'sent';
            });

            try {
                $notificationContext->handle(new stdClass(), static function (): never {
                    throw new RuntimeException('Queued mail failed.');
                });
            } catch (RuntimeException $exception) {
                expect($exception->getMessage())->toBe('Queued mail failed.');
            }
        });

        expect($urlGenerator->formatRoot($urlGenerator->formatScheme()))->toBe($originalOrigin);
    } finally {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($tenants as $tenant) {
            $tenant->run(static function () use ($tenantPrivatePath): void {
                Storage::disk('tenant-private')->delete($tenantPrivatePath);
            });
        }

        foreach ($tenantConnectionNames as $connectionName) {
            DB::purge($connectionName);
            config(['database.connections.' . $connectionName => null]);
        }

        DB::purge('tenant');
        config(['database.default' => $originalDefaultConnectionName]);
        DB::setDefaultConnection($originalDefaultConnectionName);
        $disposableDatabases->cleanup();
        DB::purge($centralConnectionName);
        config([
            'database.connections.' . $centralConnectionName => $originalCentralConnectionConfiguration,
            'tenancy.database.central_connection' => $originalCentralConnectionName,
            'session.connection' => $originalSessionConnection,
            'tenancy.central_domains' => $originalCentralDomains,
            'tenancy.central_hosts' => $originalCentralHosts,
            'tenancy.allow_legacy_model_connection' => true,
            'tenancy.allow_legacy_test_routes' => true,
        ]);
    }
});

test('tenant parent domains cannot overlap or be nested', function (): void {
    config(['tenancy.central_domains' => ['example.test', 'app.example.test']]);

    expect(fn() => (new TenancyServiceProvider(app()))->boot())
        ->toThrow(LogicException::class, 'Tenant parent domains must not overlap or be nested.');
});
