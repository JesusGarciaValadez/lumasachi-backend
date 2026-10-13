<?php

declare(strict_types=1);

namespace Tests\Support\Tenancy;

use App\Services\Tenancy\PostgreSQLTenantDatabaseManager;
use RuntimeException;
use Stancl\Tenancy\Contracts\ManagesDatabaseUsers;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;

final class OnboardingTrackingTenantDatabaseManager implements ManagesDatabaseUsers
{
    public static ?DisposablePostgresDatabases $databases = null;

    public static bool $failNextCreate = false;

    private ?PostgreSQLTenantDatabaseManager $manager = null;

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        self::$databases?->registerTenantProvisioningAttempt($tenant);

        if (self::$failNextCreate) {
            self::$failNextCreate = false;

            throw new RuntimeException('Injected one-time database provisioning failure.');
        }

        return $this->manager()->createDatabase($tenant);
    }

    private function manager(): PostgreSQLTenantDatabaseManager
    {
        if ($this->manager === null) {
            $this->manager = app(PostgreSQLTenantDatabaseManager::class);
        }

        return $this->manager;
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        return $this->manager()->deleteDatabase($tenant);
    }

    public function databaseExists(string $name): bool
    {
        return $this->manager()->databaseExists($name);
    }

    /**
     * @param array<string, mixed> $baseConfig
     * @return array<string, mixed>
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        return $this->manager()->makeConnectionConfig($baseConfig, $databaseName);
    }

    public function setConnection(string $connection): void
    {
        $this->manager()->setConnection($connection);
    }

    public function createUser(DatabaseConfig $databaseConfig): bool
    {
        return $this->manager()->createUser($databaseConfig);
    }

    public function deleteUser(DatabaseConfig $databaseConfig): bool
    {
        return $this->manager()->deleteUser($databaseConfig);
    }

    public function userExists(string $username): bool
    {
        return $this->manager()->userExists($username);
    }
}
