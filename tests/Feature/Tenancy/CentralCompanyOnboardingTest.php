<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\CentralSuperAdministrator;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ServiceCatalog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAdministratorInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\Support\Tenancy\DisposablePostgresDatabases;
use Tests\Support\Tenancy\OnboardingTrackingTenantDatabaseManager;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

/** @return array<string, mixed> */
function prepareCompanyOnboardingEnvironment(): array
{
    $databases = new DisposablePostgresDatabases;
    $centralConnectionName = 'company_onboarding_central_test';
    $originalConfiguration = [];
    $configurationKeys = [
        'database.default',
        'database.connections.tenant',
        'database.connections.' . $centralConnectionName,
        'queue.connections.database.connection',
        'queue.connections.database-central.connection',
        'queue.batching.database',
        'queue.failed.database',
        'tenancy.database.central_connection',
        'tenancy.database.template_tenant_connection',
        'tenancy.database.managers.pgsql',
        'tenancy.allow_legacy_model_connection',
        'tenancy.allow_legacy_test_routes',
        'auth.passwords.central_super_administrators.connection',
        'session.connection',
        'session.driver',
    ];

    foreach ($configurationKeys as $key) {
        $originalConfiguration[$key] = config($key);
    }

    try {
        $databases->assertCanProvisionTestTenantDatabases();
        [$centralDatabaseName] = $databases->createPair();
        $centralConfiguration = (new ConfigurationUrlParser)->parseConfiguration(DB::connection()->getConfig());
        unset($centralConfiguration['url']);
        $centralConfiguration['database'] = $centralDatabaseName;
        $centralConfiguration['name'] = $centralConnectionName;

        $centralHosts = config('tenancy.central_hosts', []);
        $centralHost = is_array($centralHosts)
            ? array_values(array_filter($centralHosts, 'is_string'))[0] ?? null
            : null;

        if (!is_string($centralHost) || $centralHost === '') {
            throw new RuntimeException('Company onboarding tests require a configured central host.');
        }

        config([
            'database.connections.' . $centralConnectionName => $centralConfiguration,
            'queue.connections.database.connection' => $centralConnectionName,
            'queue.connections.database-central.connection' => $centralConnectionName,
            'queue.batching.database' => $centralConnectionName,
            'queue.failed.database' => $centralConnectionName,
            'tenancy.database.central_connection' => $centralConnectionName,
            'tenancy.database.template_tenant_connection' => $originalConfiguration['database.default'],
            'tenancy.database.managers.pgsql' => OnboardingTrackingTenantDatabaseManager::class,
            'tenancy.allow_legacy_model_connection' => false,
            'tenancy.allow_legacy_test_routes' => false,
            'auth.passwords.central_super_administrators.connection' => $centralConnectionName,
            'session.connection' => $centralConnectionName,
            'session.driver' => 'database',
        ]);

        DB::purge($centralConnectionName);
        OnboardingTrackingTenantDatabaseManager::$databases = $databases;
        OnboardingTrackingTenantDatabaseManager::$failNextCreate = false;

        if (Artisan::call('tenancy:migrate-central') !== 0) {
            throw new RuntimeException('Central onboarding test migrations failed.');
        }

        return [
            'databases' => $databases,
            'central_connection_name' => $centralConnectionName,
            'central_host' => $centralHost,
            'original_configuration' => $originalConfiguration,
        ];
    } catch (Throwable $exception) {
        cleanupCompanyOnboardingEnvironment([
            'databases' => $databases,
            'central_connection_name' => $centralConnectionName,
            'original_configuration' => $originalConfiguration,
        ]);

        throw $exception;
    }
}

/** @param array<string, mixed> $environment */
function cleanupCompanyOnboardingEnvironment(array $environment): void
{
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Auth::forgetGuards();
    DB::purge('tenant');

    $centralConnectionName = $environment['central_connection_name'] ?? null;

    if (is_string($centralConnectionName)) {
        DB::purge($centralConnectionName);
    }

    $originalConfiguration = $environment['original_configuration'] ?? null;

    if (is_array($originalConfiguration)) {
        config($originalConfiguration);

        if (is_string($originalConfiguration['database.default'] ?? null)) {
            DB::setDefaultConnection($originalConfiguration['database.default']);
        }
    }

    $databases = $environment['databases'] ?? null;

    if ($databases instanceof DisposablePostgresDatabases) {
        $databases->cleanup();
    }

    OnboardingTrackingTenantDatabaseManager::$databases = null;
    OnboardingTrackingTenantDatabaseManager::$failNextCreate = false;
}

function companyOnboardingPayload(string $subdomain = 'north-shop'): array
{
    $parentDomains = config('tenancy.central_domains', []);
    $parentDomain = is_array($parentDomains)
        ? array_values(array_filter(
        $parentDomains,
        static fn(mixed $domain): bool => is_string($domain) && filter_var($domain, FILTER_VALIDATE_IP) === false,
    ))[0] ?? null
        : null;

    if (!is_string($parentDomain)) {
        throw new RuntimeException('A non-IP tenant parent domain is required for company onboarding tests.');
    }

    return [
        'company_name' => 'North Shop',
        'subdomain' => $subdomain,
        'parent_domain' => $parentDomain,
        'administrator_first_name' => 'Alex',
        'administrator_last_name' => 'Administrator',
        'administrator_email' => 'alex@example.test',
    ];
}

beforeEach(function (): void {
    $this->companyOnboardingEnvironment = prepareCompanyOnboardingEnvironment();
});

afterEach(function (): void {
    if (is_array($this->companyOnboardingEnvironment ?? null)) {
        cleanupCompanyOnboardingEnvironment($this->companyOnboardingEnvironment);
    }
});

test('central super administrator provisions a company and sends a tenant-bound expiring invitation', function (): void {
    Notification::fake();
    $environment = $this->companyOnboardingEnvironment;
    $centralAdministrator = CentralSuperAdministrator::factory()->create();
    $payload = companyOnboardingPayload();
    $tenantHost = $payload['subdomain'] . '.' . $payload['parent_domain'];

    $loginResponse = $this->post(route('central.login.store', ['centralHost' => $environment['central_host']]), [
        'email' => $centralAdministrator->email,
        'password' => 'password',
    ])->assertRedirect(route('central.dashboard', ['centralHost' => $environment['central_host']]));
    $centralSessionCookie = $loginResponse->getCookie(config('session.cookie'));

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->post(route('central.companies.store', ['centralHost' => $environment['central_host']]), $payload)
        ->assertRedirect(route('central.companies.index', ['centralHost' => $environment['central_host']]));

    $company = Company::query()->where('name', 'North Shop')->firstOrFail();
    $tenant = Tenant::query()->whereKey($company->uuid)->firstOrFail();
    $invitationUrl = null;
    $onboarding = DB::connection($environment['central_connection_name'])
        ->table('tenant_onboardings')
        ->where('tenant_id', $tenant->getTenantKey())
        ->first();

    expect($company->is_active)->toBeTrue()
        ->and($tenant->provisioning_status)->toBe('ready')
        ->and($tenant->ready_at)->not->toBeNull()
        ->and($tenant->domains()->pluck('domain')->all())->toBe([$tenantHost])
        ->and($onboarding->created_by_central_actor_uuid)->toBe($centralAdministrator->uuid)
        ->and($onboarding->administrator_uuid)->not->toBeNull();

    $tenant->run(function () use ($tenantHost, &$invitationUrl): void {
        $administrator = User::query()->where('email', 'alex@example.test')->firstOrFail();

        expect($administrator->role)->toBe(UserRole::ADMINISTRATOR)
            ->and($administrator->is_active)->toBeTrue()
            ->and($administrator->email_verified_at)->toBeNull()
            ->and($administrator->must_change_password)->toBeTrue()
            ->and(CompanySetting::query()->value('name'))->toBe('North Shop')
            ->and(ServiceCatalog::query()->exists())->toBeTrue();

        Notification::assertSentTo(
            $administrator,
            TenantAdministratorInvitationNotification::class,
            function (TenantAdministratorInvitationNotification $notification, array $channels) use ($administrator, $tenantHost, &$invitationUrl): bool {
                $actionUrl = $notification->toMail($administrator)->actionUrl;
                $invitationUrl = is_string($actionUrl) ? $actionUrl : null;
                $urlParts = is_string($actionUrl) ? parse_url($actionUrl) : false;
                $signatureIsValid = is_string($actionUrl)
                    && URL::hasValidSignature(Request::create($actionUrl));
                $hostChangeInvalidatesSignature = is_string($actionUrl)
                    && !URL::hasValidSignature(Request::create(str_replace(
                        $tenantHost,
                        'other.' . $tenantHost,
                        $actionUrl,
                    )));

                return in_array('mail', $channels, true)
                    && is_array($urlParts)
                    && ($urlParts['host'] ?? null) === $tenantHost
                    && $signatureIsValid
                    && $hostChangeInvalidatesSignature
                    && ($urlParts['path'] ?? null) === route(
                        'verification.verify',
                        ['id' => $administrator->getKey(), 'hash' => sha1($administrator->email)],
                        false,
                    )
                    && isset($urlParts['query'])
                    && str_contains($urlParts['query'], 'expires=')
                    && str_contains($urlParts['query'], 'signature=');
            },
        );
    });

    if (!is_string($invitationUrl)) {
        throw new RuntimeException('The tenant administrator invitation did not include a verification URL.');
    }

    $verificationResponse = $this->get($invitationUrl)
        ->assertRedirect(route('password.edit', ['required' => 1], false));
    $sessionCookie = $verificationResponse->getCookie(config('session.cookie'));

    if ($sessionCookie === null) {
        throw new RuntimeException('Accepting the invitation did not establish the tenant session.');
    }

    $newPassword = 'North-shop-secret-789!';
    $this->withCookie(config('session.cookie'), $sessionCookie->getValue())
        ->put('http://' . $tenantHost . '/settings/password', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertRedirect();

    $expiredInvitationUrl = null;
    $tenant->run(function () use ($newPassword, $tenantHost, &$expiredInvitationUrl): void {
        $administrator = User::query()->where('email', 'alex@example.test')->firstOrFail();

        expect($administrator->hasVerifiedEmail())->toBeTrue()
            ->and($administrator->must_change_password)->toBeFalse()
            ->and(Hash::check($newPassword, $administrator->password))->toBeTrue();

        $applicationUrl = parse_url((string)config('app.url'));
        $scheme = is_array($applicationUrl) && in_array($applicationUrl['scheme'] ?? null, ['https', 'http'], true)
            ? $applicationUrl['scheme']
            : 'https';
        $port = is_array($applicationUrl) && isset($applicationUrl['port']) ? ':' . $applicationUrl['port'] : '';
        $urlGenerator = new UrlGenerator(
            Route::getRoutes(),
            Request::create($scheme . '://' . $tenantHost . $port),
            (string)config('app.asset_url'),
        );
        $urlGenerator->setKeyResolver(static fn(): string => (string)config('app.key'));
        $expiredInvitationUrl = $urlGenerator->temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            [
                'id' => $administrator->getKey(),
                'hash' => sha1($administrator->getEmailForVerification()),
            ],
        );
    });

    if (!is_string($expiredInvitationUrl)) {
        throw new RuntimeException('The expired tenant administrator invitation could not be generated.');
    }

    $this->get($expiredInvitationUrl)->assertForbidden();
});

test('onboarding queues one tenant-bound invitation atomically and retry does not queue a duplicate', function (): void {
    $environment = $this->companyOnboardingEnvironment;
    $centralAdministrator = CentralSuperAdministrator::factory()->create();
    $payload = companyOnboardingPayload('queued-invitation-shop');
    $loginResponse = $this->post(route('central.login.store', ['centralHost' => $environment['central_host']]), [
        'email' => $centralAdministrator->email,
        'password' => 'password',
    ])->assertRedirect(route('central.dashboard', ['centralHost' => $environment['central_host']]));
    $centralSessionCookie = $loginResponse->getCookie(config('session.cookie'));

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->post(route('central.companies.store', ['centralHost' => $environment['central_host']]), $payload)
        ->assertRedirect(route('central.companies.index', ['centralHost' => $environment['central_host']]));

    $company = Company::query()->where('name', 'North Shop')->firstOrFail();
    $tenant = Tenant::query()->whereKey($company->uuid)->firstOrFail();
    $centralConnection = DB::connection($environment['central_connection_name']);
    $onboarding = $centralConnection->table('tenant_onboardings')->where('tenant_id', $tenant->getTenantKey())->first();
    $jobs = $centralConnection->table('jobs')->get();
    $jobPayload = json_decode((string)$jobs->first()?->payload, true);

    expect($tenant->provisioning_status)->toBe('ready')
        ->and($onboarding->invitation_queued_at)->not->toBeNull()
        ->and($jobs)->toHaveCount(1)
        ->and($jobPayload)->toBeArray()
        ->and($jobPayload['tenant_id'] ?? null)->toBe($tenant->getTenantKey());

    $tenant->forceFill(['provisioning_status' => 'failed', 'ready_at' => null])->save();
    $company->forceFill(['is_active' => false])->save();

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->post(route('central.companies.onboarding.retry', [
            'centralHost' => $environment['central_host'],
            'company' => $company->uuid,
        ]))->assertRedirect(route('central.companies.index', ['centralHost' => $environment['central_host']]));

    expect($centralConnection->table('jobs')->count())->toBe(1)
        ->and($tenant->fresh()->provisioning_status)->toBe('ready')
        ->and($company->fresh()->is_active)->toBeTrue();
});

test('failed provisioning stays hidden and retry resumes without duplicating its company or administrator', function (): void {
    Notification::fake();
    $environment = $this->companyOnboardingEnvironment;
    $centralAdministrator = CentralSuperAdministrator::factory()->create();
    $payload = companyOnboardingPayload('retry-shop');
    $tenantHost = $payload['subdomain'] . '.' . $payload['parent_domain'];
    OnboardingTrackingTenantDatabaseManager::$failNextCreate = true;

    $loginResponse = $this->post(route('central.login.store', ['centralHost' => $environment['central_host']]), [
        'email' => $centralAdministrator->email,
        'password' => 'password',
    ])->assertRedirect(route('central.dashboard', ['centralHost' => $environment['central_host']]));
    $centralSessionCookie = $loginResponse->getCookie(config('session.cookie'));

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->post(route('central.companies.store', ['centralHost' => $environment['central_host']]), $payload)
        ->assertRedirect(route('central.companies.index', ['centralHost' => $environment['central_host']]));

    $company = Company::query()->where('name', 'North Shop')->firstOrFail();
    $tenant = Tenant::query()->whereKey($company->uuid)->firstOrFail();
    $onboarding = DB::connection($environment['central_connection_name'])
        ->table('tenant_onboardings')
        ->where('tenant_id', $tenant->getTenantKey())
        ->first();

    expect($tenant->provisioning_status)->toBe('failed')
        ->and($company->is_active)->toBeFalse()
        ->and($onboarding->attempt_count)->toBe(1)
        ->and($onboarding->last_failed_stage)->toBe('database');

    $this->get('http://' . $tenantHost . '/')
        ->assertNotFound();

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->post(route('central.companies.onboarding.retry', [
            'centralHost' => $environment['central_host'],
            'company' => $company->uuid,
        ]))->assertRedirect(route('central.companies.index', ['centralHost' => $environment['central_host']]));

    $company->refresh();
    $tenant->refresh();
    $onboarding = DB::connection($environment['central_connection_name'])
        ->table('tenant_onboardings')
        ->where('tenant_id', $tenant->getTenantKey())
        ->first();

    expect(Company::query()->count())->toBe(1)
        ->and($tenant->domains()->count())->toBe(1)
        ->and($tenant->provisioning_status)->toBe('ready')
        ->and($company->is_active)->toBeTrue()
        ->and($onboarding->attempt_count)->toBe(2);

    $tenant->run(function (): void {
        expect(User::query()->where('email', 'alex@example.test')->count())->toBe(1);
    });
});

test('retry refuses to overwrite existing tenant catalog data when its seed checkpoint is absent', function (): void {
    Notification::fake();
    $environment = $this->companyOnboardingEnvironment;
    $centralAdministrator = CentralSuperAdministrator::factory()->create();
    $payload = companyOnboardingPayload('catalog-shop');

    $loginResponse = $this->post(route('central.login.store', ['centralHost' => $environment['central_host']]), [
        'email' => $centralAdministrator->email,
        'password' => 'password',
    ])->assertRedirect(route('central.dashboard', ['centralHost' => $environment['central_host']]));
    $centralSessionCookie = $loginResponse->getCookie(config('session.cookie'));

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->post(route('central.companies.store', ['centralHost' => $environment['central_host']]), $payload)
        ->assertRedirect(route('central.companies.index', ['centralHost' => $environment['central_host']]));

    $company = Company::query()->where('name', 'North Shop')->firstOrFail();
    $tenant = Tenant::query()->whereKey($company->uuid)->firstOrFail();

    $tenant->run(function (): void {
        ServiceCatalog::query()->orderBy('id')->firstOrFail()->update(['base_price' => 9876.54]);
    });

    DB::connection($environment['central_connection_name'])->table('tenant_onboardings')
        ->where('tenant_id', $tenant->getTenantKey())
        ->update(['defaults_seeded_at' => null]);
    $tenant->forceFill(['provisioning_status' => 'failed', 'ready_at' => null])->save();
    $company->forceFill(['is_active' => false])->save();

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->post(route('central.companies.onboarding.retry', [
            'centralHost' => $environment['central_host'],
            'company' => $company->uuid,
        ]))->assertRedirect(route('central.companies.index', ['centralHost' => $environment['central_host']]));

    $onboarding = DB::connection($environment['central_connection_name'])
        ->table('tenant_onboardings')
        ->where('tenant_id', $tenant->getTenantKey())
        ->first();

    expect($tenant->fresh()->provisioning_status)->toBe('failed')
        ->and($onboarding->last_failed_stage)->toBe('defaults');

    $tenant->run(function (): void {
        expect((float)ServiceCatalog::query()->orderBy('id')->firstOrFail()->base_price)->toBe(9876.54)
            ->and(User::query()->where('email', 'alex@example.test')->count())->toBe(1);
    });
});
