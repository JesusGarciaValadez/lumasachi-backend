<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\UserRole;
use App\Models\CentralSuperAdministrator;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ServiceCatalog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantAdministratorInvitationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Stancl\Tenancy\Contracts\ManagesDatabaseUsers;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Throwable;

final class CompanyOnboardingService
{
    private const LOCK_TIMEOUT_MINUTES = 60;

    /**
     * @param array{
     *     company_name: string,
     *     subdomain: string,
     *     parent_domain: string,
     *     administrator_first_name: string,
     *     administrator_last_name: string,
     *     administrator_email: string
     * } $data
     */
    public function create(array $data, CentralSuperAdministrator $actor): Company
    {
        $centralConnection = $this->centralConnectionName();
        $tenantId = (string)Str::uuid7();
        $administratorUuid = (string)Str::uuid7();
        $tenantDomain = mb_strtolower($data['subdomain'] . '.' . $data['parent_domain']);

        DB::connection($centralConnection)->transaction(function () use ($data, $actor, $centralConnection, $tenantId, $administratorUuid, $tenantDomain): void {
            $company = new Company;
            $company->forceFill([
                'name' => $data['company_name'],
                'is_active' => false,
                'uuid' => $tenantId,
            ])->save();

            $tenant = new Tenant;
            $tenant->forceFill([
                'id' => $tenantId,
                'provisioning_status' => 'pending',
                'ready_at' => null,
            ])->save();
            $tenant->createDomain($tenantDomain);
            $tenant->database()->makeCredentials();

            DB::connection($centralConnection)->table('tenant_onboardings')->insert([
                'tenant_id' => $tenantId,
                'tenant_domain' => $tenantDomain,
                'administrator_first_name' => $data['administrator_first_name'],
                'administrator_last_name' => $data['administrator_last_name'],
                'administrator_email' => $data['administrator_email'],
                'administrator_uuid' => $administratorUuid,
                'created_by_central_actor_uuid' => $actor->uuid,
                'attempt_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->provision($tenantId);

        return Company::query()->where('uuid', $tenantId)->firstOrFail();
    }

    private function centralConnectionName(): string
    {
        return (string)config('tenancy.database.central_connection', config('database.default'));
    }

    private function provision(string $tenantId): void
    {
        $lockToken = $this->acquireProvisioningLock($tenantId);

        if ($lockToken === null) {
            return;
        }

        $stage = 'database';

        try {
            $tenant = Tenant::query()->findOrFail($tenantId);
            $onboarding = $this->onboarding($tenantId);

            $stage = 'database';
            $this->provisionDatabase($tenant, $onboarding, $lockToken);

            $stage = 'migrations';
            if ($onboarding->migrationsCompletedAt === null) {
                $this->runInTenantContext($tenant, function (): void {
                    $exitCode = Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => [database_path('migrations/tenant')],
                        '--realpath' => true,
                        '--force' => true,
                        '--no-interaction' => true,
                    ]);

                    if ($exitCode !== 0) {
                        throw new RuntimeException('Tenant migrations did not complete successfully.');
                    }
                });
                $this->markCheckpoint($tenantId, $lockToken, 'migrations_completed_at');
                $onboarding = $this->onboarding($tenantId);
            }

            $stage = 'defaults';
            if ($onboarding->defaultsSeededAt === null) {
                $this->seedDefaults($tenant);
                $this->markCheckpoint($tenantId, $lockToken, 'defaults_seeded_at');
                $onboarding = $this->onboarding($tenantId);
            }

            $stage = 'company_settings';
            if ($onboarding->companySettingsCreatedAt === null) {
                $this->createCompanySettings($tenant, $onboarding, $lockToken);
                $onboarding = $this->onboarding($tenantId);
            }

            $stage = 'administrator';
            if ($onboarding->administratorCreatedAt === null) {
                $this->createAdministrator($tenant, $onboarding, $lockToken);
                $onboarding = $this->onboarding($tenantId);
            }

            $stage = 'invitation';
            if ($onboarding->invitationQueuedAt === null) {
                $this->queueInvitationAndMarkReady($tenant, $onboarding, $lockToken);

                return;
            }

            $this->markReady($tenantId, $lockToken);
        } catch (Throwable $exception) {
            $this->markFailed($tenantId, $lockToken, $stage, $exception);
            report($exception);
        }
    }

    private function acquireProvisioningLock(string $tenantId): ?string
    {
        $centralConnection = $this->centralConnectionName();

        return DB::connection($centralConnection)->transaction(function () use ($centralConnection, $tenantId): ?string {
            $onboardingRow = DB::connection($centralConnection)
                ->table('tenant_onboardings')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if ($onboardingRow === null) {
                throw new RuntimeException('Tenant onboarding record not found.');
            }

            $onboarding = TenantOnboardingRecord::fromDatabaseRow($onboardingRow);

            $tenant = Tenant::query()->findOrFail($tenantId);

            if ($tenant->provisioning_status === 'ready' && $tenant->ready_at !== null) {
                return null;
            }

            $lockIsActive = $onboarding->provisioningLockToken !== null
                && $onboarding->provisioningLockToken !== ''
                && $onboarding->provisioningLockedAt !== null
                && CarbonImmutable::parse($onboarding->provisioningLockedAt)->greaterThan(now()->subMinutes(self::LOCK_TIMEOUT_MINUTES));

            if ($lockIsActive) {
                return null;
            }

            $lockToken = (string)Str::uuid7();
            DB::connection($centralConnection)->table('tenant_onboardings')
                ->where('tenant_id', $tenantId)
                ->update([
                    'attempt_count' => $onboarding->attemptCount + 1,
                    'provisioning_lock_token' => $lockToken,
                    'provisioning_locked_at' => now(),
                    'last_failed_stage' => null,
                    'last_failure_type' => null,
                    'updated_at' => now(),
                ]);

            Tenant::query()->whereKey($tenantId)->update([
                'provisioning_status' => 'pending',
                'ready_at' => null,
            ]);
            Company::query()->where('uuid', $tenantId)->update(['is_active' => false]);

            return $lockToken;
        });
    }

    private function onboarding(string $tenantId): TenantOnboardingRecord
    {
        $row = DB::connection($this->centralConnectionName())
            ->table('tenant_onboardings')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($row === null) {
            throw new RuntimeException('Tenant onboarding record not found.');
        }

        return TenantOnboardingRecord::fromDatabaseRow($row);
    }

    private function provisionDatabase(Tenant $tenant, TenantOnboardingRecord $onboarding, string $lockToken): void
    {
        $tenant->database()->makeCredentials();
        $manager = $tenant->database()->manager();

        if (!$manager instanceof ManagesDatabaseUsers) {
            throw new RuntimeException('Tenant onboarding requires a database manager that provisions isolated tenant credentials.');
        }

        $databaseName = $tenant->database()->getName();

        if (!is_string($databaseName) || $databaseName === '') {
            throw new RuntimeException('The tenant database name was not generated.');
        }

        $databaseExists = $manager->databaseExists($databaseName);

        if ($onboarding->databaseCreatedAt !== null) {
            if (!$databaseExists) {
                throw new RuntimeException('The recorded tenant database is missing; refusing to recreate it automatically.');
            }

            return;
        }

        $databaseCreationWasStarted = $onboarding->databaseCreationStartedAt !== null;

        if ($databaseExists && !$databaseCreationWasStarted) {
            throw new RuntimeException('An untracked tenant database already exists; manual ownership review is required.');
        }

        if (!$databaseExists) {
            $this->markCheckpoint($tenant->getTenantKey(), $lockToken, 'database_creation_started_at');
            $databaseCreationWasStarted = true;
        }

        try {
            $created = $manager->createDatabase($tenant);
        } catch (TenantDatabaseAlreadyExistsException $exception) {
            if (!$databaseCreationWasStarted || !$manager->databaseExists($databaseName)) {
                throw $exception;
            }

            $created = true;
        }

        if (!$created || !$manager->databaseExists($databaseName)) {
            throw new RuntimeException('The tenant database manager did not confirm database creation.');
        }

        $this->markCheckpoint($tenant->getTenantKey(), $lockToken, 'database_created_at');
    }

    private function markCheckpoint(string $tenantId, string $lockToken, string $column): void
    {
        $centralConnection = $this->centralConnectionName();
        $updated = DB::connection($centralConnection)->table('tenant_onboardings')
            ->where('tenant_id', $tenantId)
            ->where('provisioning_lock_token', $lockToken)
            ->update([
                $column => now(),
                'provisioning_locked_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('Tenant onboarding lost its provisioning lease while recording progress.');
        }
    }

    /** @param callable(): mixed $callback */
    private function runInTenantContext(Tenant $tenant, callable $callback): mixed
    {
        $callbackException = null;
        $result = null;

        $tenant->run(function () use ($callback, &$callbackException, &$result): void {
            try {
                $result = $callback();
            } catch (Throwable $exception) {
                $callbackException = $exception;
            }
        });

        if ($callbackException instanceof Throwable) {
            throw $callbackException;
        }

        return $result;
    }

    private function seedDefaults(Tenant $tenant): void
    {
        $this->runInTenantContext($tenant, function (): void {
            if (ServiceCatalog::query()->exists()) {
                throw new RuntimeException('Unmarked tenant catalog data exists; refusing to reseed or overwrite it.');
            }

            DB::connection('tenant')->transaction(function (): void {
                $exitCode = Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => \Database\Seeders\TenantDefaultsSeeder::class,
                    '--force' => true,
                    '--no-interaction' => true,
                ]);

                if ($exitCode !== 0 || !ServiceCatalog::query()->exists()) {
                    throw new RuntimeException('Tenant default reference data did not seed successfully.');
                }
            });
        });
    }

    private function createCompanySettings(Tenant $tenant, TenantOnboardingRecord $onboarding, string $lockToken): void
    {
        $this->runInTenantContext($tenant, function () use ($tenant, $onboarding): void {
            $settings = CompanySetting::query()->get();

            if ($settings->count() > 1) {
                throw new RuntimeException('Multiple tenant company settings rows exist; manual review is required.');
            }

            if ($settings->isEmpty()) {
                if ($onboarding->companySettingsCreatedAt !== null) {
                    throw new RuntimeException('The recorded tenant company settings row is missing.');
                }

                CompanySetting::query()->create(['name' => $tenant->company()->value('name')]);
            } elseif ($settings->first()->name !== $tenant->company()->value('name')) {
                throw new RuntimeException('Existing tenant company settings do not match the central company name.');
            }
        });

        $this->markCheckpoint($tenant->getTenantKey(), $lockToken, 'company_settings_created_at');
    }

    private function createAdministrator(Tenant $tenant, TenantOnboardingRecord $onboarding, string $lockToken): void
    {
        $this->runInTenantContext($tenant, function () use ($onboarding): void {
            $user = User::withTrashed()->where('uuid', $onboarding->administratorUuid)->first();

            if ($user === null) {
                $matchingEmail = User::withTrashed()
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($onboarding->administratorEmail)])
                    ->exists();

                if ($matchingEmail) {
                    throw new RuntimeException('An untracked tenant user already uses the first administrator email.');
                }

                $user = User::query()->create([
                    'uuid' => $onboarding->administratorUuid,
                    'first_name' => $onboarding->administratorFirstName,
                    'last_name' => $onboarding->administratorLastName,
                    'email' => $onboarding->administratorEmail,
                    'password' => Str::random(64),
                    'must_change_password' => true,
                    'role' => UserRole::ADMINISTRATOR,
                    'is_active' => true,
                    'activated_at' => now(),
                    'email_verified_at' => null,
                ]);
            }

            if ($user->trashed()
                || $user->email !== $onboarding->administratorEmail
                || $user->first_name !== $onboarding->administratorFirstName
                || $user->last_name !== $onboarding->administratorLastName
                || $user->role !== UserRole::ADMINISTRATOR
                || !$user->is_active
                || !$user->must_change_password
                || $user->email_verified_at !== null) {
                throw new RuntimeException('The recorded first administrator does not match the expected invited account.');
            }
        });

        $this->markCheckpoint($tenant->getTenantKey(), $lockToken, 'administrator_created_at');
    }

    private function queueInvitationAndMarkReady(Tenant $tenant, TenantOnboardingRecord $onboarding, string $lockToken): void
    {
        $centralConnection = $this->centralConnectionName();

        DB::connection($centralConnection)->transaction(function () use ($tenant, $onboarding, $lockToken): void {
            $this->runInTenantContext($tenant, function () use ($onboarding): void {
                $user = User::query()->where('uuid', $onboarding->administratorUuid)->firstOrFail();
                $notification = (new TenantAdministratorInvitationNotification($onboarding->tenantDomain))
                    ->onConnection('database');

                Notification::send($user, $notification);
            });

            $this->markCheckpoint($tenant->getTenantKey(), $lockToken, 'invitation_queued_at');
            $this->markReady($tenant->getTenantKey(), $lockToken);
        });
    }

    private function markReady(string $tenantId, string $lockToken): void
    {
        $centralConnection = $this->centralConnectionName();
        DB::connection($centralConnection)->transaction(function () use ($centralConnection, $tenantId, $lockToken): void {
            $updated = DB::connection($centralConnection)->table('tenant_onboardings')
                ->where('tenant_id', $tenantId)
                ->where('provisioning_lock_token', $lockToken)
                ->update([
                    'provisioning_lock_token' => null,
                    'provisioning_locked_at' => null,
                    'last_failed_stage' => null,
                    'last_failure_type' => null,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new RuntimeException('Tenant onboarding lost its provisioning lease before completion.');
            }

            Tenant::query()->whereKey($tenantId)->update([
                'provisioning_status' => 'ready',
                'ready_at' => now(),
            ]);
            Company::query()->where('uuid', $tenantId)->update(['is_active' => true]);
        });
    }

    private function markFailed(string $tenantId, string $lockToken, string $stage, Throwable $exception): void
    {
        $centralConnection = $this->centralConnectionName();

        try {
            DB::connection($centralConnection)->transaction(function () use ($centralConnection, $tenantId, $lockToken, $stage, $exception): void {
                $updated = DB::connection($centralConnection)->table('tenant_onboardings')
                    ->where('tenant_id', $tenantId)
                    ->where('provisioning_lock_token', $lockToken)
                    ->update([
                        'provisioning_lock_token' => null,
                        'provisioning_locked_at' => null,
                        'last_failed_stage' => $stage,
                        'last_failure_type' => mb_substr($exception::class, 0, 255),
                        'updated_at' => now(),
                    ]);

                if ($updated !== 1) {
                    return;
                }

                Tenant::query()->whereKey($tenantId)->update([
                    'provisioning_status' => 'failed',
                    'ready_at' => null,
                ]);
                Company::query()->where('uuid', $tenantId)->update(['is_active' => false]);
            });
        } catch (Throwable $statusException) {
            report($statusException);
        }
    }

    public function retry(Company $company): Company
    {
        $this->provision((string)$company->uuid);

        return $company->fresh() ?? $company;
    }
}
