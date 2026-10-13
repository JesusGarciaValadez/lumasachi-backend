<?php

declare(strict_types=1);

namespace Tests;

use App\Models\CentralSuperAdministrator;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Database\Seeders\TenantDefaultsSeeder;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use RuntimeException;
use Tests\Support\Tenancy\DisposablePostgresDatabases;
use Throwable;

abstract class DuskTestCase extends BaseTestCase
{
    private const DUSK_APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    private const DUSK_TENANT_DOMAIN = 'tenant.dusk';

    private const DUSK_OTHER_TENANT_DOMAIN = 'other.dusk';

    public ?Tenant $duskTenant = null;

    public ?Company $duskCompany = null;

    public ?Tenant $duskOtherTenant = null;

    public ?CentralSuperAdministrator $duskCentralAdministrator = null;

    public ?Company $duskOtherCompany = null;

    private ?DisposablePostgresDatabases $duskDisposableDatabases = null;

    /** @var array<string, mixed>|null */
    private ?array $duskOriginalConfiguration = null;

    private ?string $duskOriginalApplicationEnvironment = null;

    private ?EncrypterContract $duskOriginalEncrypter = null;

    private ?string $duskCompanyUuid = null;

    private ?string $duskOtherCompanyUuid = null;

    protected function setUp(): void
    {
        parent::setUp();

        tenancy()->initialize($this->prepareDuskTenantFixture());
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupDuskTenantFixture();
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    final public static function prepare(): void
    {
        if (!static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    final public function prepareDuskTenantFixture(): Tenant
    {
        if ($this->duskTenant !== null) {
            return $this->duskTenant;
        }

        $defaultConnectionName = (string)config('database.default');
        $configurationKeys = [
            'app.env',
            'app.key',
            'cache.default',
            'database.default',
            'database.tenant_test_database_allowed_host',
            'database.connections.' . $defaultConnectionName,
            'database.connections.tenant',
            'database.connections.tenant_template',
            'tenancy.database.central_connection',
            'tenancy.database.template_tenant_connection',
            'tenancy.central_domains',
            'tenancy.central_hosts',
            'tenancy.allow_legacy_model_connection',
            'tenancy.allow_legacy_test_routes',
        ];
        $this->duskOriginalConfiguration = [];

        foreach ($configurationKeys as $key) {
            $this->duskOriginalConfiguration[$key] = config($key);
        }

        $this->duskOriginalApplicationEnvironment = app()->environment();
        $this->duskOriginalEncrypter = app(EncrypterContract::class);
        $this->duskDisposableDatabases = new DisposablePostgresDatabases;

        try {
            app()->detectEnvironment(static fn(): string => 'testing');

            $testDatabaseConfiguration = $this->duskOriginalConfiguration['database.connections.' . $defaultConnectionName];

            if (!is_array($testDatabaseConfiguration) || ($testDatabaseConfiguration['driver'] ?? null) !== 'pgsql') {
                throw new RuntimeException('Tenant-host Dusk tests require the configured PostgreSQL test connection.');
            }

            unset($testDatabaseConfiguration['url']);
            $testDatabaseConfiguration['database'] = 'testing';
            $tenantTemplateConfiguration = $testDatabaseConfiguration;
            $tenantTemplateConfiguration['search_path'] = 'public';

            config([
                'app.env' => 'testing',
                'app.key' => self::DUSK_APP_KEY,
                'cache.default' => 'array',
                'database.connections.' . $defaultConnectionName => $testDatabaseConfiguration,
                'database.connections.tenant_template' => $tenantTemplateConfiguration,
                'database.default' => $defaultConnectionName,
                'database.tenant_test_database_allowed_host' => 'pgsql',
                'tenancy.allow_legacy_model_connection' => false,
                'tenancy.allow_legacy_test_routes' => false,
                'tenancy.central_domains' => ['dusk'],
                'tenancy.central_hosts' => ['dusk'],
                'tenancy.database.central_connection' => $defaultConnectionName,
                'tenancy.database.template_tenant_connection' => 'tenant_template',
            ]);
            DB::purge($defaultConnectionName);
            DB::setDefaultConnection($defaultConnectionName);

            $duskKey = base64_decode(mb_substr(self::DUSK_APP_KEY, mb_strlen('base64:')), true);

            if (!is_string($duskKey)) {
                throw new RuntimeException('The Dusk test application key is not valid base64.');
            }

            Crypt::swap(new Encrypter($duskKey, (string)config('app.cipher')));

            $this->duskDisposableDatabases->assertCanProvisionTestTenantDatabases();

            DB::statement('CREATE SCHEMA IF NOT EXISTS lumasachi_dusk');
            $testDatabaseConfiguration['search_path'] = 'lumasachi_dusk';
            config(['database.connections.' . $defaultConnectionName => $testDatabaseConfiguration]);
            DB::purge($defaultConnectionName);
            DB::setDefaultConnection($defaultConnectionName);

            if (Artisan::call('tenancy:migrate-central', ['--no-interaction' => true]) !== 0) {
                throw new RuntimeException('The tenant-host Dusk central migrations failed: ' . Artisan::output());
            }

            $company = Company::query()->create([
                'is_active' => false,
                'name' => 'Dusk Company',
            ]);
            $this->duskCompanyUuid = (string)$company->uuid;
            $this->duskCompany = $company;
            $this->duskCentralAdministrator = CentralSuperAdministrator::factory()->create([
                'email' => 'dusk-central-' . Str::uuid7() . '@example.test',
                'password' => 'password',
            ]);

            $tenant = new Tenant;
            $tenant->forceFill([
                'id' => $this->duskCompanyUuid,
                'provisioning_status' => 'pending',
                'ready_at' => null,
            ])->save();
            $tenant->createDomain(self::DUSK_TENANT_DOMAIN);
            $tenant->database()->makeCredentials();
            $tenant->save();
            $this->duskTenant = $tenant;

            $this->duskDisposableDatabases->registerTenantProvisioningAttempt($tenant);
            $databaseWasCreated = $tenant->database()->manager()->createDatabase($tenant);

            if (!$databaseWasCreated) {
                throw new RuntimeException('The tenant-host Dusk database could not be provisioned.');
            }

            $this->duskDisposableDatabases->registerProvisionedTenant($tenant);

            $tenant->run(function (): void {
                $migrationExitCode = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--force' => true,
                    '--no-interaction' => true,
                    '--path' => [database_path('migrations/tenant')],
                    '--realpath' => true,
                ]);

                if ($migrationExitCode !== 0) {
                    throw new RuntimeException('The tenant-host Dusk tenant migrations failed: ' . Artisan::output());
                }

                $seederExitCode = Artisan::call('db:seed', [
                    '--class' => TenantDefaultsSeeder::class,
                    '--database' => 'tenant',
                    '--force' => true,
                    '--no-interaction' => true,
                ]);

                if ($seederExitCode !== 0) {
                    throw new RuntimeException('The tenant-host Dusk defaults could not be seeded: ' . Artisan::output());
                }

                CompanySetting::query()->create(['name' => 'Dusk Company']);
            });

            $tenant->forceFill([
                'provisioning_status' => 'ready',
                'ready_at' => now(),
            ])->save();
            $company->forceFill(['is_active' => true])->save();

            $otherCompany = Company::query()->create([
                'is_active' => false,
                'name' => 'Dusk Other Company',
            ]);
            $this->duskOtherCompanyUuid = (string)$otherCompany->uuid;
            $this->duskOtherCompany = $otherCompany;

            $otherTenant = new Tenant;
            $otherTenant->forceFill([
                'id' => $this->duskOtherCompanyUuid,
                'provisioning_status' => 'pending',
                'ready_at' => null,
            ])->save();
            $otherTenant->createDomain(self::DUSK_OTHER_TENANT_DOMAIN);
            $otherTenant->database()->makeCredentials();
            $otherTenant->save();
            $this->duskDisposableDatabases->registerTenantProvisioningAttempt($otherTenant);

            if (!$otherTenant->database()->manager()->createDatabase($otherTenant)) {
                throw new RuntimeException('The second tenant-host Dusk database could not be provisioned.');
            }

            $this->duskDisposableDatabases->registerProvisionedTenant($otherTenant);

            $otherTenant->run(function (): void {
                $migrationExitCode = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--force' => true,
                    '--no-interaction' => true,
                    '--path' => [database_path('migrations/tenant')],
                    '--realpath' => true,
                ]);

                if ($migrationExitCode !== 0) {
                    throw new RuntimeException('The second Dusk tenant migrations failed: ' . Artisan::output());
                }

                $seederExitCode = Artisan::call('db:seed', [
                    '--class' => TenantDefaultsSeeder::class,
                    '--database' => 'tenant',
                    '--force' => true,
                    '--no-interaction' => true,
                ]);

                if ($seederExitCode !== 0) {
                    throw new RuntimeException('The second Dusk tenant defaults could not be seeded: ' . Artisan::output());
                }

                CompanySetting::query()->create(['name' => 'Dusk Other Company']);
            });

            $otherTenant->forceFill([
                'provisioning_status' => 'ready',
                'ready_at' => now(),
            ])->save();
            $otherCompany->forceFill(['is_active' => true])->save();
            $this->duskOtherTenant = $otherTenant;

            DB::connection($defaultConnectionName)->table('tenant_onboardings')->insert([
                'tenant_id' => $this->duskCompanyUuid,
                'tenant_domain' => self::DUSK_TENANT_DOMAIN,
                'administrator_first_name' => 'Dusk',
                'administrator_last_name' => 'Administrator',
                'administrator_email' => 'dusk-first-admin-' . Str::uuid7() . '@example.test',
                'administrator_uuid' => Str::uuid7()->toString(),
                'created_by_central_actor_uuid' => $this->duskCentralAdministrator->uuid,
                'attempt_count' => 1,
                'database_creation_started_at' => now(),
                'database_created_at' => now(),
                'migrations_completed_at' => now(),
                'defaults_seeded_at' => now(),
                'company_settings_created_at' => now(),
                'administrator_created_at' => now(),
                'invitation_queued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $tenant;
        } catch (Throwable $exception) {
            $this->cleanupDuskTenantFixture();

            throw $exception;
        }
    }

    final public function runInDuskTenantContext(Closure $callback): mixed
    {
        $tenant = $this->duskTenant;

        if (!$tenant instanceof Tenant) {
            throw new RuntimeException('Prepare the Dusk tenant fixture before creating tenant records.');
        }

        $alreadyInitialized = tenancy()->initialized;

        if ($alreadyInitialized && tenancy()->tenant?->getTenantKey() !== $tenant->getTenantKey()) {
            throw new RuntimeException('The active Dusk tenant context does not match this test fixture.');
        }

        if (!$alreadyInitialized) {
            tenancy()->initialize($tenant);
        }

        try {
            return $callback();
        } finally {
            if (!$alreadyInitialized) {
                tenancy()->end();
            }
        }
    }

    final public function centralAdministratorEntersDuskTenant(Browser $browser): void
    {
        $administrator = $this->duskCentralAdministrator;
        $company = $this->duskCompany;

        if (!$administrator instanceof CentralSuperAdministrator || !$company instanceof Company) {
            throw new RuntimeException('Prepare the central administrator and tenant fixture before browser navigation.');
        }

        $browser->visit('http://dusk/login')
            ->waitFor('@login-email', 10)
            ->type('@login-email', $administrator->email)
            ->type('@login-password', 'password')
            ->click('@login-submit')
            ->waitUntilEnabled('@login-submit', 10)
            ->visit('http://dusk/companies')
            ->waitFor('@company-enter-' . $company->uuid, 10)
            ->click('@company-enter-' . $company->uuid)
            ->waitForText('Confirm company access', 10)
            ->waitUntilEnabled('#continue', 10)
            ->click('#continue')
            ->waitForLocation('/orders', 10);
    }

    final public function tenantUserLogsIn(Browser $browser, User $user, bool $requiresPasswordChange = false): void
    {
        $browser->visit('/login');

        if ($browser->element('@login-email') === null) {
            $browser->click('@user-menu-trigger')
                ->click('@user-logout')
                ->waitForLocation('/', 10)
                ->visit('/login');
        }

        $browser->waitFor('@login-email', 10)
            ->type('@login-email', $user->email)
            ->type('@login-password', 'password')
            ->click('@login-submit')
            ->waitForLocation($requiresPasswordChange ? '/settings/password' : '/dashboard', 10);
    }

    final public function cleanupDuskTenantFixture(): void
    {
        if ($this->duskOriginalConfiguration === null) {
            return;
        }

        try {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $centralConnectionName = config('tenancy.database.central_connection');
            $tenantUuids = array_values(array_filter([
                $this->duskCompanyUuid,
                $this->duskOtherCompanyUuid,
            ], static fn(?string $uuid): bool => $uuid !== null));

            if (is_string($centralConnectionName)) {
                DB::purge('tenant');

                foreach ($tenantUuids as $tenantUuid) {
                    DB::connection($centralConnectionName)->table('domains')->where('tenant_id', $tenantUuid)->delete();
                    DB::connection($centralConnectionName)->table('tenants')->where('id', $tenantUuid)->delete();
                    DB::connection($centralConnectionName)->table('companies')->where('uuid', $tenantUuid)->delete();
                }
            }

            if (is_string($centralConnectionName) && $this->duskCentralAdministrator instanceof CentralSuperAdministrator) {
                DB::connection($centralConnectionName)->table('central_super_administrators')
                    ->where('uuid', $this->duskCentralAdministrator->uuid)
                    ->delete();
            }
        } finally {
            try {
                $this->duskDisposableDatabases?->cleanup();
            } finally {
                $defaultConnectionName = (string)($this->duskOriginalConfiguration['database.default'] ?? 'pgsql');
                DB::purge('tenant');
                DB::purge($defaultConnectionName);
                config($this->duskOriginalConfiguration);
                DB::setDefaultConnection($defaultConnectionName);

                if ($this->duskOriginalApplicationEnvironment !== null) {
                    $originalEnvironment = $this->duskOriginalApplicationEnvironment;
                    app()->detectEnvironment(static fn(): string => $originalEnvironment);
                }

                if ($this->duskOriginalEncrypter !== null) {
                    Crypt::swap($this->duskOriginalEncrypter);
                }

                $this->duskTenant = null;
                $this->duskCompany = null;
                $this->duskOtherTenant = null;
                $this->duskOtherCompany = null;
                $this->duskCentralAdministrator = null;
                $this->duskCompanyUuid = null;
                $this->duskOtherCompanyUuid = null;
                $this->duskDisposableDatabases = null;
                $this->duskOriginalConfiguration = null;
                $this->duskOriginalApplicationEnvironment = null;
                $this->duskOriginalEncrypter = null;
            }
        }
    }

    protected function baseUrl(): string
    {
        return 'http://' . self::DUSK_TENANT_DOMAIN;
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--ignore-certificate-errors',
            '--allow-insecure-localhost',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
