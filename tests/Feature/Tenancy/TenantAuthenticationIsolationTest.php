<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('tenant authentication, sessions, password resets, and cookie state changes stay isolated by host', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases();
    $centralConnectionName = 'tenant_auth_central_' . bin2hex(random_bytes(6));
    $tenantConnectionNames = [
        'alpha' => 'tenant_auth_alpha',
        'beta' => 'tenant_auth_beta',
    ];
    $provisionerConnectionName = (string)config('tenancy.database.provisioner_connection');
    $configurationKeys = [
        'database.default',
        'database.connections.tenant',
        'tenancy.database.central_connection',
        'tenancy.central_domains',
        'tenancy.central_hosts',
        'tenancy.allow_legacy_model_connection',
        'tenancy.allow_legacy_test_routes',
        'auth.passwords.users.connection',
        'session.connection',
        'session.driver',
        'session.domain',
        'session.cookie',
        'sanctum.stateful',
    ];
    $originalConfiguration = [];

    foreach ($configurationKeys as $configurationKey) {
        $originalConfiguration[$configurationKey] = config($configurationKey);
    }

    $defaultConnectionName = (string)$originalConfiguration['database.default'];
    $connectionNames = array_values(array_unique([
        $defaultConnectionName,
        $centralConnectionName,
        ...array_values($tenantConnectionNames),
        $provisionerConnectionName,
    ]));
    $originalConnectionConfigurations = [];

    foreach ($connectionNames as $connectionName) {
        $originalConnectionConfigurations[$connectionName] = config('database.connections.' . $connectionName);
    }

    $tenants = [];
    $tenantDatabaseNames = [];

    try {
        $testAdminConfiguration = $originalConnectionConfigurations[$defaultConnectionName];

        if (!is_array($testAdminConfiguration)) {
            throw new RuntimeException('The configured test database connection is unavailable.');
        }

        unset($testAdminConfiguration['url']);
        $testAdminConfiguration['database'] = 'testing';
        config(['database.connections.' . $defaultConnectionName => $testAdminConfiguration]);
        DB::purge($defaultConnectionName);
        DB::setDefaultConnection($defaultConnectionName);

        expect(DB::connection()->selectOne('select current_database() as database_name')?->database_name)
            ->toBe('testing');

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
            'auth.passwords.users.connection' => 'tenant',
            'session.driver' => 'database',
            'session.domain' => '.example.test',
            'session.cookie' => 'tenant_auth_session',
            'sanctum.stateful' => ['alpha.example.test', 'beta.example.test'],
        ]);
        DB::purge($centralConnectionName);

        $centralMigrationExitCode = Artisan::call('tenancy:migrate-central', [
            '--no-interaction' => true,
        ]);

        if ($centralMigrationExitCode !== 0) {
            throw new RuntimeException('The central migration command failed: ' . Artisan::output());
        }

        foreach ($tenantConnectionNames as $subdomain => $connectionName) {
            $company = Company::query()->create(['name' => ucfirst($subdomain) . ' Company']);
            $tenant = Tenant::factory()->make(['id' => $company->uuid]);
            $tenant->save();
            $tenant->domains()->create(['domain' => $subdomain . '.example.test']);
            $tenant->database()->makeCredentials();
            $disposableDatabases->registerTenantProvisioningAttempt($tenant);
            $tenant->database()->manager()->createDatabase($tenant);
            $disposableDatabases->registerProvisionedTenant($tenant);

            $tenantDatabaseNames[$subdomain] = $tenant->database()->getName();
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

        $email = 'shared-address@example.test';
        $alphaPassword = 'Alpha-secret-123!';
        $betaPassword = 'Beta-secret-456!';
        $users = [];

        foreach ($tenants as $subdomain => $tenant) {
            tenancy()->initialize($tenant);

            try {
                $users[$subdomain] = User::factory()->create([
                    'email' => $email,
                    'password' => $subdomain === 'alpha' ? $alphaPassword : $betaPassword,
                    'role' => UserRole::ADMINISTRATOR,
                    'is_active' => true,
                    'activated_at' => now(),
                    'email_verified_at' => now(),
                ]);
            } finally {
                tenancy()->end();
            }
        }

        $alphaUser = $users['alpha'];
        $betaUser = $users['beta'];

        expect($alphaUser->id)->toBe($betaUser->id)
            ->and($alphaUser->email)->toBe($betaUser->email);

        $hasSession = static function (string $databaseName, string $sessionId) use ($disposableDatabases): bool {
            return $disposableDatabases->withDatabase($databaseName, static function (PDO $pdo) use ($sessionId): bool {
                $statement = $pdo->prepare('SELECT EXISTS (SELECT 1 FROM sessions WHERE id = :session_id)');

                if ($statement === false) {
                    throw new RuntimeException('Could not prepare a tenant session lookup.');
                }

                $statement->execute(['session_id' => $sessionId]);

                return (bool)$statement->fetchColumn();
            });
        };

        $resetTokenHash = static function (string $databaseName, string $userEmail) use ($disposableDatabases): ?string {
            return $disposableDatabases->withDatabase($databaseName, static function (PDO $pdo) use ($userEmail): ?string {
                $statement = $pdo->prepare('SELECT token FROM password_reset_tokens WHERE email = :email');

                if ($statement === false) {
                    throw new RuntimeException('Could not prepare a tenant password-reset token lookup.');
                }

                $statement->execute(['email' => $userEmail]);
                $tokenHash = $statement->fetchColumn();

                return is_string($tokenHash) ? $tokenHash : null;
            });
        };

        Notification::fake();
        $this->withCredentials()
            ->postJson('http://alpha.example.test/forgot-password', ['email' => $email])
            ->assertOk()
            ->assertJsonPath('code', 'auth.reset_link_sent');

        $resetToken = null;
        Notification::assertSentTo(
            $alphaUser,
            ResetPassword::class,
            static function (ResetPassword $notification) use (&$resetToken): bool {
                $resetToken = $notification->token;

                return true;
            },
        );

        $alphaResetTokenHash = $resetTokenHash($tenantDatabaseNames['alpha'], $email);

        expect($resetToken)->toBeString()->not->toBeEmpty()
            ->and($alphaResetTokenHash)->toBeString()
            ->and(Hash::check($resetToken, (string)$alphaResetTokenHash))->toBeTrue()
            ->and($resetTokenHash($tenantDatabaseNames['beta'], $email))->toBeNull();

        $alphaThrottleKey = 'tenant|' . $tenants['alpha']->getTenantKey() . '|' . $email . '|127.0.0.1';

        foreach (range(1, 5) as $_attempt) {
            $this->postJson('http://alpha.example.test/login', [
                'email' => $email,
                'password' => 'wrong-alpha-password',
            ])->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $tokenThrottleEmail = 'token-rate-limit@example.test';

        foreach (range(1, 5) as $_attempt) {
            $this->postJson('http://alpha.example.test/api/v1/sanctum/token', [
                'email' => $tokenThrottleEmail,
                'password' => 'wrong-alpha-password',
                'device_name' => 'tenant rate-limit test',
            ])->assertUnauthorized();
        }

        $this->postJson('http://alpha.example.test/api/v1/sanctum/token', [
            'email' => $tokenThrottleEmail,
            'password' => 'wrong-alpha-password',
            'device_name' => 'tenant rate-limit test',
        ])->assertTooManyRequests();

        $alphaTokenResponse = $this->postJson('http://alpha.example.test/api/v1/sanctum/token', [
            'email' => $email,
            'password' => $alphaPassword,
            'device_name' => 'alpha tenant isolation test',
        ])->assertSuccessful();
        $alphaToken = mb_trim((string)$alphaTokenResponse->getContent(), '"');

        expect($alphaToken)->not->toBeEmpty();

        $this->withToken($alphaToken)
            ->getJson('http://alpha.example.test/api/v1/orders')
            ->assertSuccessful();

        $this->withToken($alphaToken)
            ->getJson('http://beta.example.test/api/v1/orders')
            ->assertUnauthorized();

        $this->postJson('http://beta.example.test/api/v1/sanctum/token', [
            'email' => $tokenThrottleEmail,
            'password' => 'wrong-beta-password',
            'device_name' => 'tenant rate-limit test',
        ])->assertUnauthorized();

        $this->postJson('http://beta.example.test/login', [
            'email' => $email,
            'password' => $betaPassword,
        ])->assertRedirect('/dashboard');

        $tenants['alpha']->run(static function () use ($alphaThrottleKey): void {
            RateLimiter::clear($alphaThrottleKey);
        });

        $this->withCredentials()
            ->postJson('http://beta.example.test/reset-password', [
                'token' => $resetToken,
                'email' => $email,
                'password' => 'Changed-secret-789!',
                'password_confirmation' => 'Changed-secret-789!',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->withCredentials()
            ->postJson('http://beta.example.test/login', [
                'email' => $email,
                'password' => $alphaPassword,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $alphaLoginResponse = $this->withCredentials()
            ->postJson('http://alpha.example.test/login', [
                'email' => $email,
                'password' => $alphaPassword,
            ])
            ->assertRedirect('/dashboard');
        $alphaSessionId = $alphaLoginResponse->getCookie('tenant_auth_session')?->getValue();

        expect($alphaSessionId)->toBeString()->not->toBeEmpty()
            ->and($hasSession($tenantDatabaseNames['alpha'], $alphaSessionId))->toBeTrue()
            ->and($hasSession($tenantDatabaseNames['beta'], $alphaSessionId))->toBeFalse();

        $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->get('http://alpha.example.test/confirm-password')
            ->assertSuccessful();

        $replayedAlphaSession = $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->get('http://beta.example.test/confirm-password');

        expect($replayedAlphaSession->baseResponse->getStatusCode())->toBe(302)
            ->and($replayedAlphaSession->headers->get('Location'))->toContain('beta.example.test/login');

        $betaLoginResponse = $this->withCredentials()
            ->postJson('http://beta.example.test/login', [
                'email' => $email,
                'password' => $betaPassword,
            ]);
        $betaLoginResponse->assertRedirect();
        $betaSessionId = $betaLoginResponse->getCookie('tenant_auth_session')?->getValue();

        expect($betaSessionId)->toBeString()->not->toBeEmpty()
            ->and($betaSessionId)->not->toBe($alphaSessionId)
            ->and($betaLoginResponse->headers->get('Location'))->toContain('beta.example.test')
            ->and($hasSession($tenantDatabaseNames['beta'], $betaSessionId))->toBeTrue()
            ->and($hasSession($tenantDatabaseNames['alpha'], $betaSessionId))->toBeFalse();

        $this->withCookie('tenant_auth_session', $betaSessionId)
            ->get('http://beta.example.test/confirm-password')
            ->assertSuccessful();

        $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->get('http://alpha.example.test/confirm-password')
            ->assertSuccessful();

        $this->app->instance(
            PreventRequestForgery::class,
            new class($this->app, $this->app->make(Encrypter::class)) extends PreventRequestForgery {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            },
        );

        $csrfPageResponse = $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->get('http://alpha.example.test/');
        $csrfPageResponse->assertSuccessful();
        $csrfToken = $csrfPageResponse->getCookie('XSRF-TOKEN')?->getValue();

        expect($csrfToken)->toBeString()->not->toBeEmpty();

        $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->withHeader('Origin', 'http://alpha.example.test')
            ->withHeader('Referer', 'http://alpha.example.test/')
            ->postJson('http://alpha.example.test/api/v1/orders', [])
            ->assertStatus(419);

        $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->withHeader(
                'X-XSRF-TOKEN',
                $this->app->make(Encrypter::class)->encrypt(
                    CookieValuePrefix::create('XSRF-TOKEN', $this->app->make(Encrypter::class)->getKey()) . $csrfToken,
                    PreventRequestForgery::serialized(),
                ),
            )
            ->postJson('http://alpha.example.test/api/v1/orders', [])
            ->assertUnprocessable();

        $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->withHeader('X-XSRF-TOKEN', 'invalid')
            ->post('http://alpha.example.test/logout')
            ->assertStatus(419);

        $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->get('http://alpha.example.test/confirm-password')
            ->assertSuccessful();

        $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->post('http://alpha.example.test/logout', ['_token' => $csrfToken])
            ->assertRedirect('/');

        $loggedOutResponse = $this->withCookie('tenant_auth_session', $alphaSessionId)
            ->get('http://alpha.example.test/confirm-password');

        expect($loggedOutResponse->baseResponse->getStatusCode())->toBe(302)
            ->and($loggedOutResponse->headers->get('Location'))->toContain('alpha.example.test/login');
    } finally {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ([$centralConnectionName, ...array_values($tenantConnectionNames), 'tenant'] as $connectionName) {
            DB::purge($connectionName);
        }

        config([
            'database.default' => $originalConfiguration['database.default'],
            'tenancy.database.central_connection' => $originalConfiguration['tenancy.database.central_connection'],
        ]);
        DB::setDefaultConnection((string)$originalConfiguration['database.default']);

        try {
            $disposableDatabases->cleanup();
        } finally {
            foreach ($originalConfiguration as $configurationKey => $configurationValue) {
                config([$configurationKey => $configurationValue]);
            }

            foreach ($originalConnectionConfigurations as $connectionName => $connectionConfiguration) {
                DB::purge($connectionName);
                config(['database.connections.' . $connectionName => $connectionConfiguration]);
            }

            DB::setDefaultConnection((string)$originalConfiguration['database.default']);
            $this->app->forgetInstance(PreventRequestForgery::class);
        }
    }
});
