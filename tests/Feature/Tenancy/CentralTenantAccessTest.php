<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\CentralSuperAdministrator;
use App\Models\CentralTenantAccessGrant;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\CentralTenantAccessService;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

/**
 * @return array{
 *     disposable_databases: DisposablePostgresDatabases,
 *     central_connection_name: string,
 *     central_host: string,
 *     tenant_parent_domain: string,
 *     central_administrator: CentralSuperAdministrator,
 *     company: Company,
 *     tenants: array{primary: Tenant, secondary: Tenant},
 *     tenant_hosts: array{primary: string, secondary: string},
 *     original_configuration: array<string, mixed>
 * }
 */
function prepareCentralTenantAccessTestEnvironment(): array
{
    $disposableDatabases = new DisposablePostgresDatabases;
    $centralConnectionName = 'central_tenant_access_test';
    $originalConfiguration = [
        'database.default' => config('database.default'),
        'database.connections.tenant' => config('database.connections.tenant'),
        'database.connections.' . $centralConnectionName => config('database.connections.' . $centralConnectionName),
        'tenancy.database.central_connection' => config('tenancy.database.central_connection'),
        'tenancy.allow_legacy_model_connection' => config('tenancy.allow_legacy_model_connection'),
        'tenancy.allow_legacy_test_routes' => config('tenancy.allow_legacy_test_routes'),
        'auth.passwords.central_super_administrators.connection' => config('auth.passwords.central_super_administrators.connection'),
        'session.connection' => config('session.connection'),
        'session.driver' => config('session.driver'),
    ];

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        [$centralDatabaseName] = $disposableDatabases->createPair();

        $centralConfiguration = (new ConfigurationUrlParser)->parseConfiguration(DB::connection()->getConfig());
        unset($centralConfiguration['url']);
        $centralConfiguration['database'] = $centralDatabaseName;
        $centralConfiguration['name'] = $centralConnectionName;

        $centralHosts = config('tenancy.central_hosts', []);
        $centralHost = is_array($centralHosts)
            ? collect($centralHosts)->first(static fn(mixed $host): bool => is_string($host) && $host !== '')
            : null;
        $parentDomains = config('tenancy.central_domains', []);
        $parentDomains = is_array($parentDomains)
            ? array_values(array_filter(
                $parentDomains,
                static fn(mixed $domain): bool => is_string($domain)
                    && $domain !== ''
                    && filter_var($domain, FILTER_VALIDATE_IP) === false,
            ))
            : [];
        $tenantParentDomain = $parentDomains[0] ?? null;

        if (!is_string($centralHost)
            || !is_string($tenantParentDomain)
            || count($parentDomains) !== 1) {
            throw new RuntimeException('The test requires a configured exact central host and exactly one non-IP tenant parent domain.');
        }

        config([
            'database.connections.' . $centralConnectionName => $centralConfiguration,
            'tenancy.database.central_connection' => $centralConnectionName,
            'tenancy.allow_legacy_model_connection' => false,
            'tenancy.allow_legacy_test_routes' => false,
            'auth.passwords.central_super_administrators.connection' => $centralConnectionName,
            'session.connection' => $centralConnectionName,
            'session.driver' => 'database',
        ]);
        DB::purge($centralConnectionName);

        $centralMigrationExitCode = Artisan::call('tenancy:migrate-central');

        if ($centralMigrationExitCode !== 0) {
            throw new RuntimeException('The central migration command failed: ' . Artisan::output());
        }

        $centralAdministrator = CentralSuperAdministrator::factory()->create([
            'email' => 'central-access@example.test',
            'password' => 'central-access-test-password',
        ]);
        $company = Company::query()->create(['name' => 'Central access test company']);

        $primaryTenant = createReadyCentralAccessTestTenant(
            $disposableDatabases,
            $company,
            'support-primary',
        );
        $secondaryCompany = Company::query()->create(['name' => 'Secondary access test company']);
        $secondaryTenant = createReadyCentralAccessTestTenant(
            $disposableDatabases,
            $secondaryCompany,
            'support-secondary',
        );

        return [
            'disposable_databases' => $disposableDatabases,
            'central_connection_name' => $centralConnectionName,
            'central_host' => $centralHost,
            'tenant_parent_domain' => $tenantParentDomain,
            'central_administrator' => $centralAdministrator,
            'company' => $company,
            'tenants' => [
                'primary' => $primaryTenant,
                'secondary' => $secondaryTenant,
            ],
            'tenant_hosts' => [
                'primary' => 'support-primary.' . $tenantParentDomain,
                'secondary' => 'support-secondary.' . $tenantParentDomain,
            ],
            'original_configuration' => $originalConfiguration,
        ];
    } catch (Throwable $exception) {
        cleanupCentralTenantAccessTestEnvironment([
            'disposable_databases' => $disposableDatabases,
            'central_connection_name' => $centralConnectionName,
            'original_configuration' => $originalConfiguration,
        ]);

        throw $exception;
    }
}

function createReadyCentralAccessTestTenant(
    DisposablePostgresDatabases $disposableDatabases,
    Company                     $company,
    string                      $subdomain,
): Tenant
{
    $tenant = Tenant::factory()->make(['id' => $company->uuid]);
    $tenant->save();
    $parentDomains = config('tenancy.central_domains', []);
    $parentDomains = is_array($parentDomains)
        ? array_values(array_filter(
            $parentDomains,
            static fn(mixed $domain): bool => is_string($domain)
                && $domain !== ''
                && filter_var($domain, FILTER_VALIDATE_IP) === false,
        ))
        : [];
    $parentDomain = $parentDomains[0] ?? null;

    if (!is_string($parentDomain) || $parentDomain === '') {
        throw new RuntimeException('Central tenant access tests require a configured non-IP tenant parent domain.');
    }

    $tenant->domains()->create(['domain' => mb_strtolower($subdomain . '.' . $parentDomain)]);
    $tenant->database()->makeCredentials();
    $disposableDatabases->registerTenantProvisioningAttempt($tenant);
    $tenant->database()->manager()->createDatabase($tenant);
    $disposableDatabases->registerProvisionedTenant($tenant);

    DB::purge('tenant');
    config(['database.connections.tenant' => $tenant->database()->connection()]);

    $tenantMigrationExitCode = Artisan::call('migrate', [
        '--database' => 'tenant',
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

    return $tenant;
}

/** @param array<string, mixed> $environment */
function cleanupCentralTenantAccessTestEnvironment(array $environment): void
{
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Auth::forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    DB::purge('tenant');

    $centralConnectionName = $environment['central_connection_name'] ?? null;

    if (is_string($centralConnectionName) && $centralConnectionName !== '') {
        DB::purge($centralConnectionName);
    }

    $originalConfiguration = $environment['original_configuration'] ?? null;

    if (is_array($originalConfiguration)) {
        config($originalConfiguration);
        DB::setDefaultConnection((string)$originalConfiguration['database.default']);
    }

    $disposableDatabases = $environment['disposable_databases'] ?? null;

    if ($disposableDatabases instanceof DisposablePostgresDatabases) {
        $disposableDatabases->cleanup();
    }
}

/** @var array<string, mixed>|null $centralTenantAccessEnvironment */
$centralTenantAccessEnvironment = null;

beforeEach(function () use (&$centralTenantAccessEnvironment): void {
    $centralTenantAccessEnvironment = prepareCentralTenantAccessTestEnvironment();
});

afterEach(function () use (&$centralTenantAccessEnvironment): void {
    if (is_array($centralTenantAccessEnvironment)) {
        cleanupCentralTenantAccessTestEnvironment($centralTenantAccessEnvironment);
    }

    $centralTenantAccessEnvironment = null;
});

test('an authenticated central administrator receives a handoff on the selected tenant subdomain', function () use (&$centralTenantAccessEnvironment): void {
    $environment = $centralTenantAccessEnvironment;
    $centralHost = $environment['central_host'];
    $centralAdministrator = $environment['central_administrator'];
    $company = $environment['company'];
    $primaryHost = $environment['tenant_hosts']['primary'];
    $sessionCookieName = config('session.cookie');

    $centralLogin = $this->post('http://' . $centralHost . '/login', [
        'email' => $centralAdministrator->email,
        'password' => 'central-access-test-password',
    ])->assertRedirect();
    $centralSessionCookie = $centralLogin->getCookie($sessionCookieName);

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $wrongCentralHost = 'unregistered.' . $environment['tenant_parent_domain'];
    $this->withCookie($sessionCookieName, $centralSessionCookie->getValue())
        ->post('http://' . $wrongCentralHost . '/companies/' . $company->id . '/enter')
        ->assertNotFound();

    $handoff = $this->withCookie($sessionCookieName, $centralSessionCookie->getValue())
        ->post('http://' . $centralHost . '/companies/' . $company->id . '/enter')
        ->assertRedirect();
    $handoffUrl = $handoff->headers->get('Location');
    $handoffParts = is_string($handoffUrl) ? parse_url($handoffUrl) : false;
    parse_str(is_array($handoffParts) ? (string)($handoffParts['fragment'] ?? '') : '', $handoffFragment);
    $grantToken = $handoffFragment['token'] ?? null;

    if (!is_array($handoffParts)
        || ($handoffParts['host'] ?? null) !== $primaryHost
        || ($handoffParts['path'] ?? null) !== '/central/entry'
        || ($handoffParts['query'] ?? '') !== ''
        || !is_string($grantToken)) {
        throw new RuntimeException('Central entry did not redirect with a fragment grant to the selected tenant subdomain.');
    }

    expect($grantToken)->toMatch('/\A[a-zA-Z0-9]{64}\z/');

    $grant = CentralTenantAccessGrant::query()
        ->where('token_hash', hash('sha256', $grantToken))
        ->firstOrFail();

    expect($grant->tenant_id)->toBe($environment['tenants']['primary']->getTenantKey())
        ->and($grant->central_actor_uuid)->toBe($centralAdministrator->uuid)
        ->and($grant->entered_at)->toBeNull()
        ->and($grant->central_origin)->toBe('http://' . $centralHost);
});

test('tenant handoff confirmation is one-time, tenant-bound, attributed, and explicitly audited', function () use (&$centralTenantAccessEnvironment): void {
    $environment = $centralTenantAccessEnvironment;
    $centralHost = $environment['central_host'];
    $centralAdministrator = $environment['central_administrator'];
    $primaryHost = $environment['tenant_hosts']['primary'];
    $secondaryHost = $environment['tenant_hosts']['secondary'];
    $sessionCookieName = config('session.cookie');

    $centralLogin = $this->post('http://' . $centralHost . '/login', [
        'email' => $centralAdministrator->email,
        'password' => 'central-access-test-password',
    ])->assertRedirect();
    $centralSessionCookie = $centralLogin->getCookie($sessionCookieName);

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $issuedGrant = app(CentralTenantAccessService::class)->issue(
        $centralAdministrator,
        $environment['tenants']['primary'],
        'http://' . $centralHost,
    );
    $grantToken = $issuedGrant['token'];
    $grant = $issuedGrant['grant'];
    $wrongCentralHost = 'unregistered.' . $environment['tenant_parent_domain'];

    $this->withCookie($sessionCookieName, '')
        ->get('http://' . $secondaryHost . '/central/entry')
        ->assertOk();
    $this->post('http://' . $secondaryHost . '/central/entry', ['token' => $grantToken])
        ->assertNotFound();
    $this->get('http://' . $wrongCentralHost . '/central/entry')
        ->assertNotFound();

    expect($grant->tenant_id)->toBe($environment['tenants']['primary']->getTenantKey())
        ->and($grant->central_actor_uuid)->toBe($centralAdministrator->uuid)
        ->and($grant->entered_at)->toBeNull();

    $confirmation = $this->get('http://' . $primaryHost . '/central/entry')
        ->assertOk()
        ->assertSeeText('Confirm company access')
        ->assertSeeText('This action is recorded.')
        ->assertDontSee($grantToken)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    $tenantPromptCookie = $confirmation->getCookie($sessionCookieName);

    if ($tenantPromptCookie === null) {
        throw new RuntimeException('The tenant confirmation page did not issue its tenant session cookie.');
    }

    $tenantEntry = $this->withCookie($sessionCookieName, $tenantPromptCookie->getValue())
        ->post('http://' . $primaryHost . '/central/entry', ['token' => $grantToken])
        ->assertRedirect('/orders');
    $tenantAccessCookie = $tenantEntry->getCookie($sessionCookieName);

    if ($tenantAccessCookie === null) {
        throw new RuntimeException('Tenant confirmation did not issue the support session cookie.');
    }

    $this->withCookie($sessionCookieName, $tenantAccessCookie->getValue())
        ->get('http://' . $primaryHost . '/orders')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Orders/Index'));

    $grant->refresh();

    expect($grant->entered_at)->not->toBeNull();

    $supportActor = $environment['tenants']['primary']->run(
        fn(): User => User::query()
            ->where('central_actor_uuid', $centralAdministrator->uuid)
            ->firstOrFail(),
    );

    expect($supportActor->isPlatformSupport())
        ->toBeTrue()
        ->and($supportActor->is_active)
        ->toBeFalse()
        ->and($supportActor->central_actor_uuid)
        ->toBe($centralAdministrator->uuid);

    $tenantUser = $environment['tenants']['primary']->run(
        fn(): User => User::factory()->create(['role' => UserRole::EMPLOYEE]),
    );

    $environment['tenants']['primary']->run(function () use ($supportActor, $tenantUser): void {
        expect($supportActor->can('viewAny', User::class))
            ->toBeTrue()
            ->and($supportActor->can('viewAdministration', $tenantUser))
            ->toBeTrue()
            ->and($supportActor->can('updateAdministration', $tenantUser))
            ->toBeTrue();
    });

    $this->withCookie($sessionCookieName, $tenantAccessCookie->getValue())
        ->get('http://' . $primaryHost . '/users')
        ->assertOk();

    $this->withCookie($sessionCookieName, $tenantAccessCookie->getValue())
        ->post('http://' . $primaryHost . '/user', [
            'first_name' => 'Central Managed',
            'last_name' => 'Employee',
            'email' => 'central-managed-employee@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::EMPLOYEE->value,
            'type' => UserType::INDIVIDUAL->value,
            'is_active' => false,
            'company_id' => null,
        ])
        ->assertRedirect('/users');

    $environment['tenants']['primary']->run(function () use ($tenantUser): void {
        $administrator = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);

        expect($administrator->can('viewAny', User::class))
            ->toBeFalse()
            ->and($administrator->can('create', User::class))
            ->toBeFalse()
            ->and($administrator->can('updateAdministration', $tenantUser))
            ->toBeFalse();
    });

    $this->postJson('http://' . $primaryHost . '/api/v1/sanctum/token', [
        'email' => $supportActor->email,
        'password' => 'any-password-must-fail',
        'device_name' => 'support actor login test',
    ])
        ->assertUnauthorized();

    $history = $environment['tenants']['primary']->run(function () use ($centralAdministrator): OrderHistory {
        $supportActor = User::query()
            ->where('central_actor_uuid', $centralAdministrator->uuid)
            ->firstOrFail();
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'created_by' => $employee->id,
            'updated_by' => $employee->id,
            'assigned_to' => $employee->id,
        ]);

        Auth::guard('web')->setUser($supportActor);

        return OrderHistory::query()->create([
            'order_id' => $order->id,
            'field_changed' => OrderHistory::FIELD_TITLE,
            'old_value' => 'Before support update',
            'new_value' => 'After support update',
            'created_by' => $employee->id,
        ]);
    });

    expect($history->created_by)->toBe($supportActor->id)
        ->and($history->central_actor_uuid)->toBe($centralAdministrator->uuid);

    $this->withCookie($sessionCookieName, $tenantAccessCookie->getValue())
        ->get('http://' . $primaryHost . '/central/entry')
        ->assertOk();
    $this->post('http://' . $primaryHost . '/central/entry', ['token' => $grantToken])
        ->assertNotFound();

    $exit = $this->withCookie($sessionCookieName, $tenantAccessCookie->getValue())
        ->post('http://' . $primaryHost . '/central/exit')
        ->assertRedirect();
    $exitUrl = $exit->headers->get('Location');
    $exitParts = is_string($exitUrl) ? parse_url($exitUrl) : false;

    expect(is_array($exitParts))->toBeTrue()
        ->and($exitParts['host'] ?? null)->toBe($centralHost)
        ->and($exitParts['path'] ?? null)->toBe('/central');

    $grant->refresh();

    expect($grant->exited_at)->not->toBeNull()
        ->and($grant->central_actor_uuid)->toBe($centralAdministrator->uuid)
        ->and($grant->central_origin)->toBe('http://' . $centralHost)
        ->and(DB::connection($environment['central_connection_name'])
            ->table('central_tenant_access_grants')
            ->where('id', $grant->getKey())
            ->whereNotNull('exited_at')
            ->exists())->toBeTrue();

    $this->withCookie($sessionCookieName, $tenantAccessCookie->getValue())
        ->get('http://' . $primaryHost . '/orders')
        ->assertRedirect('/login');
});

test('deactivating the central administrator revokes an active tenant support session', function () use (&$centralTenantAccessEnvironment): void {
    $environment = $centralTenantAccessEnvironment;
    $centralHost = $environment['central_host'];
    $centralAdministrator = $environment['central_administrator'];
    $primaryHost = $environment['tenant_hosts']['primary'];
    $sessionCookieName = config('session.cookie');

    $centralLogin = $this->post('http://' . $centralHost . '/login', [
        'email' => $centralAdministrator->email,
        'password' => 'central-access-test-password',
    ])->assertRedirect();
    $centralSessionCookie = $centralLogin->getCookie($sessionCookieName);

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $issuedGrant = app(CentralTenantAccessService::class)->issue(
        $centralAdministrator,
        $environment['tenants']['primary'],
        'http://' . $centralHost,
    );
    $grantToken = $issuedGrant['token'];

    $prompt = $this->withCookie($sessionCookieName, '')
        ->get('http://' . $primaryHost . '/central/entry')
        ->assertOk();
    $promptCookie = $prompt->getCookie($sessionCookieName);

    if ($promptCookie === null) {
        throw new RuntimeException('The tenant confirmation page did not issue its tenant session cookie.');
    }

    $entry = $this->withCookie($sessionCookieName, $promptCookie->getValue())
        ->post('http://' . $primaryHost . '/central/entry', ['token' => $grantToken])
        ->assertRedirect('/orders');
    $tenantAccessCookie = $entry->getCookie($sessionCookieName);

    if ($tenantAccessCookie === null) {
        throw new RuntimeException('Tenant confirmation did not issue the support session cookie.');
    }

    $grant = $issuedGrant['grant'];
    $centralAdministrator->forceFill(['is_active' => false])->save();

    $this->withCookie($sessionCookieName, $tenantAccessCookie->getValue())
        ->get('http://' . $primaryHost . '/orders')
        ->assertForbidden();

    $grant->refresh();

    expect($grant->entered_at)->not->toBeNull()
        ->and($grant->exited_at)->toBeNull()
        ->and($grant->central_actor_uuid)->toBe($centralAdministrator->uuid);
});

test('central logout revokes an active tenant support session', function () use (&$centralTenantAccessEnvironment): void {
    $environment = $centralTenantAccessEnvironment;
    $centralHost = $environment['central_host'];
    $centralAdministrator = $environment['central_administrator'];
    $primaryHost = $environment['tenant_hosts']['primary'];
    $sessionCookieName = config('session.cookie');

    $centralLogin = $this->post('http://' . $centralHost . '/login', [
        'email' => $centralAdministrator->email,
        'password' => 'central-access-test-password',
    ])->assertRedirect();
    $centralCookie = $centralLogin->getCookie($sessionCookieName);

    if ($centralCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $issuedGrant = app(CentralTenantAccessService::class)->issue(
        $centralAdministrator,
        $environment['tenants']['primary'],
        'http://' . $centralHost,
    );
    $grant = $issuedGrant['grant'];
    $prompt = $this->withCookie($sessionCookieName, '')
        ->get('http://' . $primaryHost . '/central/entry')
        ->assertOk();
    $promptCookie = $prompt->getCookie($sessionCookieName);

    if ($promptCookie === null) {
        throw new RuntimeException('The tenant confirmation page did not issue its session cookie.');
    }

    $entry = $this->withCookie($sessionCookieName, $promptCookie->getValue())
        ->post('http://' . $primaryHost . '/central/entry', ['token' => $issuedGrant['token']])
        ->assertRedirect('/orders');
    $tenantCookie = $entry->getCookie($sessionCookieName);

    if ($tenantCookie === null) {
        throw new RuntimeException('Tenant confirmation did not issue the support session cookie.');
    }

    $this->withCookie($sessionCookieName, $tenantCookie->getValue())
        ->get('http://' . $primaryHost . '/orders')
        ->assertOk();

    $this->withCookie($sessionCookieName, $centralCookie->getValue())
        ->post('http://' . $centralHost . '/logout')
        ->assertRedirect();

    $grant->refresh();

    expect($grant->revoked_at)->not->toBeNull();

    $this->withCookie($sessionCookieName, $tenantCookie->getValue())
        ->get('http://' . $primaryHost . '/orders')
        ->assertForbidden();
});
