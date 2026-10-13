<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\CentralSuperAdministrator;
use App\Models\CentralTenantAccessGrant;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

/**
 * @return array<string, mixed>
 */
function prepareCentralSuperAdministratorBrowserAuthEnvironment(): array
{
    $disposableDatabases = new DisposablePostgresDatabases;
    $centralConnectionName = 'central_browser_auth_test';
    $tenantConnectionName = 'central_browser_auth_tenant_test';
    $originalConfiguration = [
        'database.default' => config('database.default'),
        'database.connections.tenant' => config('database.connections.tenant'),
        'database.connections.' . $centralConnectionName => config('database.connections.' . $centralConnectionName),
        'database.connections.' . $tenantConnectionName => config('database.connections.' . $tenantConnectionName),
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
        $centralDomains = config('tenancy.central_domains', []);
        $centralHost = is_array($centralHosts) ? reset($centralHosts) : false;
        $tenantParentDomains = is_array($centralDomains)
            ? array_values(array_filter(
                $centralDomains,
                static fn(mixed $domain): bool => is_string($domain)
                    && $domain !== ''
                    && filter_var($domain, FILTER_VALIDATE_IP) === false,
            ))
            : [];
        $tenantParentDomain = reset($tenantParentDomains);

        if (!is_string($centralHost) || $centralHost === '' || !is_string($tenantParentDomain) || $tenantParentDomain === '') {
            throw new RuntimeException('Central auth tests require at least one exact central host and tenant parent domain.');
        }

        config([
            'database.connections.' . $centralConnectionName => $centralConfiguration,
            'tenancy.database.central_connection' => $centralConnectionName,
            'tenancy.allow_legacy_model_connection' => false,
            'tenancy.allow_legacy_test_routes' => false,
            'auth.passwords.central_super_administrators.connection' => $centralConnectionName,
            'session.driver' => 'database',
        ]);

        DB::purge($centralConnectionName);

        $centralMigrationExitCode = Artisan::call('tenancy:migrate-central');

        if ($centralMigrationExitCode !== 0) {
            throw new RuntimeException('Central auth test migration failed: ' . Artisan::output());
        }

        $company = Company::query()->create(['name' => 'Central auth test company']);
        $tenant = Tenant::factory()->make(['id' => $company->uuid]);
        $tenant->save();
        $tenant->domains()->create(['domain' => 'auth-slice.' . $tenantParentDomain]);
        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);
        $tenant->database()->manager()->createDatabase($tenant);
        $disposableDatabases->registerProvisionedTenant($tenant);

        config(['database.connections.' . $tenantConnectionName => $tenant->database()->connection()]);
        DB::purge($tenantConnectionName);

        $tenantMigrationExitCode = Artisan::call('migrate', [
            '--database' => $tenantConnectionName,
            '--force' => true,
            '--no-interaction' => true,
            '--path' => [database_path('migrations/tenant')],
            '--realpath' => true,
        ]);

        if ($tenantMigrationExitCode !== 0) {
            throw new RuntimeException('Tenant auth test migration failed: ' . Artisan::output());
        }

        $tenant->forceFill([
            'provisioning_status' => 'ready',
            'ready_at' => now(),
        ])->save();

        $sharedEmail = 'same.identity@example.test';
        $tenantPassword = 'tenant-independent-password';

        $tenant->run(static function () use ($sharedEmail, $tenantPassword): void {
            User::factory()->create([
                'email' => $sharedEmail,
                'password' => $tenantPassword,
                'role' => UserRole::EMPLOYEE,
            ]);
        });

        return [
            'disposable_databases' => $disposableDatabases,
            'central_connection_name' => $centralConnectionName,
            'tenant_connection_name' => $tenantConnectionName,
            'central_host' => $centralHost,
            'tenant_host' => 'auth-slice.' . $tenantParentDomain,
            'tenant' => $tenant,
            'shared_email' => $sharedEmail,
            'tenant_password' => $tenantPassword,
            'original_configuration' => $originalConfiguration,
        ];
    } catch (Throwable $exception) {
        cleanupCentralSuperAdministratorBrowserAuthEnvironment([
            'disposable_databases' => $disposableDatabases,
            'central_connection_name' => $centralConnectionName,
            'tenant_connection_name' => $tenantConnectionName,
            'original_configuration' => $originalConfiguration,
        ]);

        throw $exception;
    }
}

/** @param array<string, mixed> $environment */
function cleanupCentralSuperAdministratorBrowserAuthEnvironment(array $environment): void
{
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Auth::forgetGuards();
    DB::purge('tenant');

    foreach (['central_connection_name', 'tenant_connection_name'] as $connectionKey) {
        $connectionName = $environment[$connectionKey] ?? null;

        if (is_string($connectionName) && $connectionName !== '') {
            DB::purge($connectionName);
        }
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

function createActiveCentralSupportGrant(CentralSuperAdministrator $administrator): CentralTenantAccessGrant
{
    $centralHost = config('tenancy.central_hosts.0');

    if (!is_string($centralHost) || $centralHost === '') {
        throw new RuntimeException('Central support grant tests require a configured central host.');
    }

    $grant = new CentralTenantAccessGrant;
    $grant->forceFill([
        'token_hash' => hash('sha256', Str::random(64)),
        'tenant_id' => (string)Str::uuid(),
        'central_actor_uuid' => $administrator->uuid,
        'central_origin' => 'http://' . $centralHost,
        'expires_at' => now()->addMinute(),
        'entered_at' => now(),
    ])->save();

    return $grant;
}

/** @var array<string, mixed>|null $centralAuthEnvironment */
$centralAuthEnvironment = null;

beforeEach(function () use (&$centralAuthEnvironment): void {
    $centralAuthEnvironment = prepareCentralSuperAdministratorBrowserAuthEnvironment();
});

afterEach(function () use (&$centralAuthEnvironment): void {
    if (is_array($centralAuthEnvironment)) {
        cleanupCentralSuperAdministratorBrowserAuthEnvironment($centralAuthEnvironment);
    }

    $centralAuthEnvironment = null;
});

test('central login authenticates only active central accounts and tenant login still works on its host', function () use (&$centralAuthEnvironment): void {
    $environment = $centralAuthEnvironment;
    $centralHost = $environment['central_host'];
    $tenantHost = $environment['tenant_host'];
    $sharedEmail = $environment['shared_email'];
    $tenantPassword = $environment['tenant_password'];

    $centralAdministrator = CentralSuperAdministrator::factory()->create([
        'email' => $sharedEmail,
        'password' => 'central-independent-password',
    ]);
    $inactiveAdministrator = CentralSuperAdministrator::factory()->create([
        'email' => 'inactive-super-admin@example.test',
        'password' => 'inactive-password',
        'is_active' => false,
    ]);

    $this->get('http://' . $centralHost . '/login')
        ->assertInertia(fn($page) => $page
            ->component('auth/Login')
            ->where('canResetPassword', true));

    $this->get('http://' . $tenantHost . '/login')
        ->assertInertia(fn($page) => $page->component('auth/Login'));

    $tenantLoginResponse = $this->post('http://' . $tenantHost . '/login', [
        'email' => $sharedEmail,
        'password' => $tenantPassword,
    ])->assertRedirect();

    expect(parse_url($tenantLoginResponse->headers->get('Location'), PHP_URL_PATH))->toBe('/dashboard');

    $limitedEmail = 'rate-limited@example.test';

    foreach (range(1, 5) as $_attempt) {
        $this->postJson('http://' . $centralHost . '/login', [
            'email' => $limitedEmail,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    expect(RateLimiter::tooManyAttempts('central|' . $limitedEmail . '|127.0.0.1', 5))->toBeTrue();

    $this->postJson('http://' . $centralHost . '/login', [
        'email' => $limitedEmail,
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('http://' . $centralHost . '/login', [
        'email' => $sharedEmail,
        'password' => $tenantPassword,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('http://' . $centralHost . '/login', [
        'email' => $inactiveAdministrator->email,
        'password' => 'inactive-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $centralLoginResponse = $this->post('http://' . $centralHost . '/login', [
        'email' => $centralAdministrator->email,
        'password' => 'central-independent-password',
    ])->assertRedirect();

    expect(parse_url($centralLoginResponse->headers->get('Location'), PHP_URL_HOST))->toBe($centralHost)
        ->and(parse_url($centralLoginResponse->headers->get('Location'), PHP_URL_PATH))->toBe('/central');

    $centralSessionCookie = $centralLoginResponse->getCookie(config('session.cookie'));

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->get('http://' . $centralHost . '/central')
        ->assertOk()
        ->assertSeeText('Central administration is ready.');

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->get('http://' . $centralHost . '/login')
        ->assertRedirect();

    $this->withCookie(config('session.cookie'), $centralSessionCookie->getValue())
        ->get('http://' . $centralHost . '/forgot-password')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auth/ForgotPassword')
            ->where('can_create_order', false)
            ->where('can_create_user', false));

});

test('central login ignores a stale tenant web authentication session', function () use (&$centralAuthEnvironment): void {
    $environment = $centralAuthEnvironment;
    $tenant = $environment['tenant'] ?? null;
    $centralConnectionName = $environment['central_connection_name'] ?? null;
    $centralHost = $environment['central_host'] ?? null;

    if (!$tenant instanceof Tenant || !is_string($centralConnectionName) || !is_string($centralHost)) {
        throw new RuntimeException('Central browser-auth test environment was not initialized.');
    }

    $tenantUserIdentifier = $tenant->run(
        static fn(): string => (string)User::query()->firstOrFail()->getAuthIdentifier(),
    );
    $webGuardSessionKey = Auth::guard('web')->getName();
    $sessionId = Str::random(40);

    DB::connection($centralConnectionName)->table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'stale-tenant-web-session-test',
        'payload' => base64_encode(serialize([$webGuardSessionKey => $tenantUserIdentifier])),
        'last_activity' => now()->getTimestamp(),
    ]);

    Auth::forgetGuards();

    $this->withCookie(config('session.cookie'), $sessionId)
        ->get('http://' . $centralHost . '/login')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auth/Login')
            ->where('auth.user', null));
});

test('central logout deletes only the central session record', function () use (&$centralAuthEnvironment): void {
    $environment = $centralAuthEnvironment;
    $centralHost = $environment['central_host'];
    $centralAdministrator = CentralSuperAdministrator::factory()->create();
    $activeSupportGrant = createActiveCentralSupportGrant($centralAdministrator);

    $loginResponse = $this->post('http://' . $centralHost . '/login', [
        'email' => $centralAdministrator->email,
        'password' => 'password',
    ])->assertRedirect();

    $centralSessionCookie = $loginResponse->getCookie(config('session.cookie'));

    if ($centralSessionCookie === null) {
        throw new RuntimeException('Central login did not issue a session cookie.');
    }

    $sessionId = $centralSessionCookie->getValue();
    $centralConnectionName = $environment['central_connection_name'];
    $tenantConnectionName = $environment['tenant_connection_name'];

    expect(DB::connection($centralConnectionName)->table('sessions')->where('id', $sessionId)->exists())->toBeTrue();

    DB::connection($tenantConnectionName)->table('sessions')->insert([
        'id' => $sessionId,
        'user_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'central-auth-isolation-test',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);

    $logoutResponse = $this->withCookie(config('session.cookie'), $sessionId)
        ->post('http://' . $centralHost . '/logout')
        ->assertRedirect();

    expect(parse_url($logoutResponse->headers->get('Location'), PHP_URL_HOST))->toBe($centralHost)
        ->and(parse_url($logoutResponse->headers->get('Location'), PHP_URL_PATH))->toBe('/login');

    expect(DB::connection($centralConnectionName)->table('sessions')->where('id', $sessionId)->exists())->toBeFalse()
        ->and(DB::connection($tenantConnectionName)->table('sessions')->where('id', $sessionId)->exists())->toBeTrue()
        ->and($activeSupportGrant->fresh()->revoked_at)->not->toBeNull();
});

test('central password reset stores and consumes tokens centrally and emails the current central host', function () use (&$centralAuthEnvironment): void {
    $environment = $centralAuthEnvironment;
    $centralHost = $environment['central_host'];
    $sharedEmail = $environment['shared_email'];
    $tenantPassword = $environment['tenant_password'];
    $centralAdministrator = CentralSuperAdministrator::factory()->create([
        'email' => $sharedEmail,
        'password' => 'before-central-reset',
    ]);
    $activeSupportGrant = createActiveCentralSupportGrant($centralAdministrator);
    $centralConnectionName = $environment['central_connection_name'];
    $oldSessionId = Str::random(40);

    DB::connection($centralConnectionName)->table('sessions')->insert([
        'id' => $oldSessionId,
        'user_id' => $centralAdministrator->getKey(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'central-password-reset-test',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);

    Notification::fake();

    $this->post('http://' . $centralHost . '/forgot-password', [
        'email' => $sharedEmail,
    ])->assertRedirect();

    $storedToken = DB::connection($centralConnectionName)
        ->table('central_password_reset_tokens')
        ->where('email', $sharedEmail)
        ->first();
    $notificationsByNotifiable = Notification::sentNotifications();
    $administratorNotifications = $notificationsByNotifiable[CentralSuperAdministrator::class][(string)$centralAdministrator->getKey()] ?? [];
    $sentNotification = collect($administratorNotifications)
        ->flatMap(static fn(array $notifications): array => $notifications)
        ->pluck('notification')
        ->first(static fn(mixed $notification): bool => $notification instanceof ResetPassword);

    if ($sentNotification === null) {
        throw new RuntimeException('Central password broker did not send a reset notification.');
    }

    $resetUrl = $sentNotification->toMail($centralAdministrator)->actionUrl;

    expect($storedToken)->not->toBeNull()
        ->and($resetUrl)->toBeString()
        ->and(parse_url($resetUrl, PHP_URL_HOST))->toBe($centralHost)
        ->and(parse_url($resetUrl, PHP_URL_PATH))->toBe('/reset-password/' . $sentNotification->token);

    $this->post('http://' . $centralHost . '/reset-password', [
        'token' => $sentNotification->token,
        'email' => $sharedEmail,
        'password' => 'after-central-reset',
        'password_confirmation' => 'after-central-reset',
    ])->assertRedirect();

    $reloadedCentralAdministrator = CentralSuperAdministrator::query()->findOrFail($centralAdministrator->getKey());
    $tenantPasswordHash = DB::connection($environment['tenant_connection_name'])
        ->table('users')
        ->where('email', $sharedEmail)
        ->value('password');

    expect(Hash::check('after-central-reset', $reloadedCentralAdministrator->password))->toBeTrue()
        ->and(Hash::check($tenantPassword, $tenantPasswordHash))->toBeTrue()
        ->and(DB::connection($centralConnectionName)->table('sessions')->where('id', $oldSessionId)->exists())->toBeFalse()
        ->and($activeSupportGrant->fresh()->revoked_at)->not->toBeNull()
        ->and(DB::connection($centralConnectionName)
            ->table('central_password_reset_tokens')
            ->where('email', $sharedEmail)
            ->exists())->toBeFalse();
});
