<?php

declare(strict_types=1);

namespace Tests\Support\Tenancy;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Throwable;

final class DisposablePostgresDatabases
{
    private const POSTGRESQL_BOOTSTRAP_SUPERUSER_OID = 10;

    private const DATABASE_PREFIX = 'lumasachi_tenant_test_';

    private const DATABASE_NAME_PATTERN = '/\Alumasachi_tenant_test_[a-f0-9]{32}\z/D';

    private const MANAGED_TENANT_DATABASE_NAME_PATTERN = '/\Alumasachi_tenant_[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D';

    private const TENANT_ROLE_NAME_PATTERN = '/\A[A-Za-z0-9]{16}\z/D';

    private const TENANT_OWNER_ROLE_NAME_PATTERN = '/\Alumasachi_owner_[0-9a-f]{32}\z/D';

    private const TEST_PROVISIONER_ROLE_NAME_PATTERN = '/\Aluma_prov_test_[a-f0-9]{16}\z/D';

    /** @var list<string> */
    private array $createdDatabaseNames = [];

    /** @var list<string> */
    private array $createdTenantRoleNames = [];

    /** @var list<string> */
    private array $createdTenantOwnerRoleNames = [];

    private ?Connection $adminConnection = null;

    /** @var array<string, mixed>|null */
    private ?array $originalProvisionerConfiguration = null;

    private ?string $originalProvisionerConnectionName = null;

    private ?string $createdTestProvisionerRoleName = null;

    private ?string $createdTestProvisionerPassword = null;

    /**
     * @return array{0: string, 1: string}
     */
    public function createPair(): array
    {
        $this->assertSafeAdminConnection();

        $adminConnection = $this->adminConnection;

        if ($adminConnection === null) {
            throw new RuntimeException('The guarded PostgreSQL test connection is unavailable for database creation.');
        }

        $adminPdo = $adminConnection->getPdo();
        $databaseNames = [];

        for ($index = 0; $index < 2; $index++) {
            $databaseName = $this->generateUnusedDatabaseName($adminPdo);

            try {
                $adminPdo->exec('CREATE DATABASE ' . $this->quoteIdentifier($databaseName));
            } catch (PDOException $exception) {
                throw new RuntimeException(
                    'Could not create a disposable tenancy test database. The configured PostgreSQL test role must have CREATEDB permission.',
                    previous: $exception,
                );
            }

            $this->createdDatabaseNames[] = $databaseName;
            $databaseNames[] = $databaseName;
        }

        return [$databaseNames[0], $databaseNames[1]];
    }

    private function assertSafeAdminConnection(): void
    {
        if (app()->environment() !== 'testing') {
            throw new RuntimeException('Refusing tenancy database lifecycle outside APP_ENV=testing.');
        }

        $connection = DB::connection();

        if (!$connection instanceof Connection) {
            throw new RuntimeException('Refusing tenancy database lifecycle without a resolved Laravel database connection.');
        }

        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Refusing tenancy database lifecycle unless the resolved database driver is pgsql.');
        }

        $expectedWriteHost = config('database.tenant_test_database_allowed_host');
        $connectionConfiguration = $connection->getConfig();
        $writeConfiguration = $connectionConfiguration['write'] ?? null;
        $writeHost = is_array($writeConfiguration)
            ? ($writeConfiguration['host'] ?? $connectionConfiguration['host'] ?? null)
            : ($connectionConfiguration['host'] ?? null);

        if (!is_string($expectedWriteHost)
            || $expectedWriteHost === ''
            || !is_string($writeHost)
            || $writeHost !== $expectedWriteHost) {
            throw new RuntimeException('Refusing tenancy database lifecycle unless the PostgreSQL write host matches the explicit test allowlist.');
        }

        $connectedDatabase = $connection->selectOne(
            'select current_database() as database_name',
            [],
            false,
        )?->database_name;

        if ($connectedDatabase !== 'testing') {
            throw new RuntimeException('Refusing tenancy database lifecycle unless the connected database is exactly testing.');
        }

        $adminRole = $connection->selectOne(
            'SELECT rolsuper FROM pg_roles WHERE rolname = current_user',
            [],
            false,
        );

        if ($adminRole === null || !$adminRole->rolsuper) {
            throw new RuntimeException('Refusing disposable PostgreSQL cleanup unless the test database role is a superuser.');
        }

        $this->adminConnection = $connection;
    }

    private function generateUnusedDatabaseName(PDO $adminPdo): string
    {
        do {
            $databaseName = self::DATABASE_PREFIX . bin2hex(random_bytes(16));
            $this->assertValidDatabaseName($databaseName);

            $statement = $adminPdo->prepare(
                'select exists (select 1 from pg_database where datname = :database_name)'
            );
            $statement->execute(['database_name' => $databaseName]);
            $alreadyExists = (bool)$statement->fetchColumn();
            $statement->closeCursor();
        } while ($alreadyExists);

        return $databaseName;
    }

    private function assertValidDatabaseName(string $databaseName): void
    {
        if (preg_match(self::DATABASE_NAME_PATTERN, $databaseName) !== 1
            && preg_match(self::MANAGED_TENANT_DATABASE_NAME_PATTERN, $databaseName) !== 1) {
            throw new RuntimeException('Refusing tenancy database operation for a name outside the approved test prefix and format.');
        }
    }

    private function quoteIdentifier(string $databaseName): string
    {
        $this->assertValidDatabaseName($databaseName);

        return '"' . str_replace('"', '""', $databaseName) . '"';
    }

    public function registerProvisionedTenant(TenantWithDatabase $tenant): void
    {
        $this->assertCanProvisionTestTenantDatabases();

        $databaseName = $tenant->database()->getName();
        $tenantKey = $tenant->getTenantKey();
        $expectedDatabaseName = (string)config('tenancy.database.prefix')
            . $tenantKey
            . (string)config('tenancy.database.suffix');

        if (!is_string($tenantKey)
            || !Uuid::isValid($tenantKey)
            || $databaseName !== $expectedDatabaseName
            || preg_match(self::MANAGED_TENANT_DATABASE_NAME_PATTERN, $databaseName) !== 1) {
            throw new RuntimeException('Refusing to register a database outside the generated tenant identifier format.');
        }

        $username = $tenant->database()->getUsername();

        if (!is_string($username) || preg_match(self::TENANT_ROLE_NAME_PATTERN, $username) !== 1) {
            throw new RuntimeException('Refusing to register a PostgreSQL role outside the generated tenant format.');
        }

        $ownerRoleName = (string)config('tenancy.database.owner_role_prefix') . str_replace('-', '', $tenantKey);

        if (preg_match(self::TENANT_OWNER_ROLE_NAME_PATTERN, $ownerRoleName) !== 1) {
            throw new RuntimeException('Refusing to register a PostgreSQL owner role outside the generated tenant format.');
        }

        $adminConnection = $this->adminConnection;
        $matchingRoleCount = $adminConnection?->table('pg_roles')
            ->whereIn('rolname', [$username, $ownerRoleName])
            ->count();

        if ($adminConnection === null
            || !$adminConnection->table('pg_database')->where('datname', $databaseName)->exists()
            || $matchingRoleCount !== 2) {
            throw new RuntimeException('Refusing to register a tenant database or role that does not exist.');
        }

        if (!in_array($databaseName, $this->createdDatabaseNames, true)
            || !in_array($username, $this->createdTenantRoleNames, true)) {
            throw new RuntimeException('Refusing to register a tenant database or runtime role not owned by this provisioning attempt.');
        }
    }

    public function assertCanProvisionTestTenantDatabases(): void
    {
        $this->assertSafeAdminConnection();

        if (app()->environment('testing')) {
            $this->bootstrapTestProvisioner();
        }

        $this->assertSafeProvisionerConnection();
    }

    private function bootstrapTestProvisioner(): void
    {
        if ($this->createdTestProvisionerRoleName !== null) {
            return;
        }

        $adminConnection = $this->adminConnection;
        $connectionName = config('tenancy.database.provisioner_connection');

        if ($adminConnection === null || !is_string($connectionName) || $connectionName === '') {
            throw new RuntimeException('Cannot prepare a restricted test provisioner without guarded PostgreSQL connections.');
        }

        $this->originalProvisionerConnectionName = $connectionName;
        $originalConfiguration = config("database.connections.{$connectionName}");

        if (!is_array($originalConfiguration)) {
            throw new RuntimeException('The configured tenant provisioner connection is unavailable for test setup.');
        }

        $this->originalProvisionerConfiguration = $originalConfiguration;
        $this->createdTestProvisionerRoleName = 'luma_prov_test_' . bin2hex(random_bytes(8));
        $this->createdTestProvisionerPassword = bin2hex(random_bytes(32));
        $quotedRoleName = $this->quoteTestProvisionerRoleName($this->createdTestProvisionerRoleName);
        $passwordLiteral = $adminConnection->getPdo()->quote($this->createdTestProvisionerPassword);

        if (!is_string($passwordLiteral)) {
            throw new RuntimeException('Could not safely quote the disposable tenant provisioner password.');
        }

        try {
            $adminConnection->statement(
                'CREATE ROLE ' . $quotedRoleName . ' LOGIN PASSWORD ' . $passwordLiteral
                . ' NOSUPERUSER CREATEDB CREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION'
            );
            $adminConnection->statement('GRANT CONNECT ON DATABASE "testing" TO ' . $quotedRoleName);

            $provisionerConfiguration = $adminConnection->getConfig();
            unset($provisionerConfiguration['url']);
            $provisionerConfiguration['database'] = 'testing';
            $provisionerConfiguration['username'] = $this->createdTestProvisionerRoleName;
            $provisionerConfiguration['password'] = $this->createdTestProvisionerPassword;
            $provisionerConfiguration['name'] = $connectionName;

            DB::purge($connectionName);
            config(['database.connections.' . $connectionName => $provisionerConfiguration]);

            $this->assertSafeProvisionerConnection();
        } catch (Throwable $exception) {
            DB::purge($connectionName);
            $cleanupFailures = [];
            try {
                $adminConnection->statement('REVOKE CONNECT ON DATABASE "testing" FROM ' . $quotedRoleName);
            } catch (Throwable $cleanupException) {
                $cleanupFailures[] = $cleanupException;
            }

            try {
                $adminConnection->statement('DROP ROLE IF EXISTS ' . $quotedRoleName);
                $this->createdTestProvisionerRoleName = null;
                $this->createdTestProvisionerPassword = null;
            } catch (Throwable $cleanupException) {
                $cleanupFailures[] = $cleanupException;
            }

            if ($this->createdTestProvisionerRoleName === null) {
                config(['database.connections.' . $connectionName => $this->originalProvisionerConfiguration]);
                $this->originalProvisionerConfiguration = null;
                $this->originalProvisionerConnectionName = null;
            }

            foreach ($cleanupFailures as $cleanupFailure) {
                report($cleanupFailure);
            }

            throw $exception;
        }
    }

    private function quoteTestProvisionerRoleName(string $roleName): string
    {
        if (preg_match(self::TEST_PROVISIONER_ROLE_NAME_PATTERN, $roleName) !== 1) {
            throw new RuntimeException('Refusing a PostgreSQL role operation outside the generated test provisioner format.');
        }

        return '"' . $roleName . '"';
    }

    private function assertSafeProvisionerConnection(): void
    {
        $connectionName = config('tenancy.database.provisioner_connection');

        if (!is_string($connectionName) || $connectionName === '') {
            throw new RuntimeException('Refusing tenant database lifecycle without an explicit provisioner connection.');
        }

        $connection = DB::connection($connectionName);

        if (!$connection instanceof Connection || $connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Refusing tenant database lifecycle unless the provisioner connection uses PostgreSQL.');
        }

        $expectedWriteHost = config('database.tenant_test_database_allowed_host');
        $connectionConfiguration = $connection->getConfig();
        $writeConfiguration = $connectionConfiguration['write'] ?? null;
        $writeHost = is_array($writeConfiguration)
            ? ($writeConfiguration['host'] ?? $connectionConfiguration['host'] ?? null)
            : ($connectionConfiguration['host'] ?? null);

        if (!is_string($expectedWriteHost)
            || $expectedWriteHost === ''
            || !is_string($writeHost)
            || $writeHost !== $expectedWriteHost) {
            throw new RuntimeException('Refusing tenant database lifecycle unless the provisioner write host matches the explicit test allowlist.');
        }

        $connectedDatabase = $connection->selectOne(
            'select current_database() as database_name',
            [],
            false,
        )?->database_name;

        if ($connectedDatabase !== 'testing') {
            throw new RuntimeException('Refusing tenant database lifecycle unless the provisioner database is exactly testing.');
        }

        $provisionerRole = $connection->selectOne(
            'SELECT rolcanlogin, rolsuper, rolcreatedb, rolcreaterole, rolreplication '
            . 'FROM pg_roles WHERE rolname = current_user',
            [],
            false,
        );

        if ($provisionerRole === null
            || !$provisionerRole->rolcanlogin
            || $provisionerRole->rolsuper
            || !$provisionerRole->rolcreatedb
            || !$provisionerRole->rolcreaterole
            || $provisionerRole->rolreplication) {
            throw new RuntimeException('The disposable PostgreSQL provisioner must be a non-superuser CREATEDB/CREATEROLE login.');
        }
    }

    public function registerTenantProvisioningAttempt(TenantWithDatabase $tenant): void
    {
        $this->assertCanProvisionTestTenantDatabases();

        $tenantKey = $tenant->getTenantKey();
        $databaseName = $tenant->database()->getName();
        $expectedDatabaseName = (string)config('tenancy.database.prefix')
            . $tenantKey
            . (string)config('tenancy.database.suffix');
        $username = $tenant->database()->getUsername();

        if (!is_string($tenantKey)
            || !Uuid::isValid($tenantKey)
            || !is_string($databaseName)
            || $databaseName !== $expectedDatabaseName
            || preg_match(self::MANAGED_TENANT_DATABASE_NAME_PATTERN, $databaseName) !== 1) {
            throw new RuntimeException('Refusing to track a tenant provisioning attempt outside the generated identifier format.');
        }

        if (!is_string($username) || preg_match(self::TENANT_ROLE_NAME_PATTERN, $username) !== 1) {
            throw new RuntimeException('Refusing to track a tenant role outside the generated format.');
        }

        $ownerRoleName = (string)config('tenancy.database.owner_role_prefix') . str_replace('-', '', $tenantKey);

        if (preg_match(self::TENANT_OWNER_ROLE_NAME_PATTERN, $ownerRoleName) !== 1) {
            throw new RuntimeException('Refusing to track a tenant owner role outside the generated identifier format.');
        }

        $adminConnection = $this->adminConnection;

        if ($adminConnection === null) {
            throw new RuntimeException('The guarded PostgreSQL test connection is unavailable for provisioning tracking.');
        }

        if (!$adminConnection->table('pg_database')->where('datname', $databaseName)->exists()) {
            $this->appendUnique($this->createdDatabaseNames, $databaseName);
        }

        $existingRoleNames = $adminConnection->table('pg_roles')
            ->whereIn('rolname', [$username, $ownerRoleName])
            ->pluck('rolname')
            ->all();

        if (!in_array($username, $existingRoleNames, true)) {
            $this->appendUnique($this->createdTenantRoleNames, $username);
        }

        if (!in_array($ownerRoleName, $existingRoleNames, true)) {
            $this->appendUnique($this->createdTenantOwnerRoleNames, $ownerRoleName);
        }
    }

    /**
     * @template TValue of string
     *
     * @param list<TValue> $values
     * @param TValue $value
     */
    private function appendUnique(array &$values, string $value): void
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    /**
     * @template TResult
     *
     * @param callable(PDO): TResult $callback
     * @return TResult
     */
    public function withDatabase(string $databaseName, callable $callback): mixed
    {
        $this->assertOwnedDatabaseName($databaseName);

        if ($this->adminConnection === null) {
            throw new RuntimeException('Create the disposable database pair before opening a tenant database.');
        }

        $configuration = array_replace($this->adminConnection->getConfig(), [
            'database' => $databaseName,
            'name' => 'tenancy_test_' . $databaseName,
        ]);
        $tenantConnection = DB::build($configuration);

        if (!$tenantConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not provide a disposable PostgreSQL test connection.');
        }

        try {
            $connectedDatabase = $tenantConnection->selectOne(
                'select current_database() as database_name',
                [],
                false,
            )?->database_name;

            if ($connectedDatabase !== $databaseName) {
                throw new RuntimeException('The tenant test connection did not resolve to its requested disposable database.');
            }

            return $callback($tenantConnection->getPdo());
        } finally {
            $tenantConnection->disconnect();
        }
    }

    private function assertOwnedDatabaseName(string $databaseName): void
    {
        $this->assertValidDatabaseName($databaseName);

        if (!in_array($databaseName, $this->createdDatabaseNames, true)) {
            throw new RuntimeException('Refusing tenancy database operation for a database not created by this helper.');
        }
    }

    public function cleanup(): void
    {
        if ($this->createdDatabaseNames === []
            && $this->createdTenantRoleNames === []
            && $this->createdTenantOwnerRoleNames === []
            && $this->createdTestProvisionerRoleName === null) {
            return;
        }

        $this->cleanupDisposableResources();
        $this->dropTestProvisionerRole();
        $this->restoreTestProvisionerConfiguration();
        $this->cleanupDisposableRoles();
    }

    private function cleanupDisposableResources(): void
    {
        $this->assertCanProvisionTestTenantDatabases();

        foreach ($this->createdDatabaseNames as $databaseName) {
            $this->assertOwnedDatabaseName($databaseName);
        }

        foreach ($this->createdTenantRoleNames as $username) {
            if (preg_match(self::TENANT_ROLE_NAME_PATTERN, $username) !== 1) {
                throw new RuntimeException('Refusing to clean up a PostgreSQL role outside the generated tenant format.');
            }
        }

        foreach ($this->createdTenantOwnerRoleNames as $ownerRoleName) {
            if (preg_match(self::TENANT_OWNER_ROLE_NAME_PATTERN, $ownerRoleName) !== 1) {
                throw new RuntimeException('Refusing to clean up a PostgreSQL owner role outside the generated tenant format.');
            }
        }

        $adminPdo = $this->adminConnection?->getPdo();

        if (!$adminPdo instanceof PDO) {
            throw new RuntimeException('The guarded PostgreSQL test connection is unavailable for database cleanup.');
        }

        $adminConnection = $this->adminConnection;

        if ($adminConnection === null) {
            throw new RuntimeException('The guarded PostgreSQL test connection is unavailable for membership cleanup.');
        }

        $failures = [];
        $remainingDatabaseNames = [];

        foreach ($this->createdDatabaseNames as $databaseName) {
            try {
                $adminPdo->exec('DROP DATABASE IF EXISTS ' . $this->quoteIdentifier($databaseName));
            } catch (Throwable $exception) {
                if ($exception instanceof PDOException && $exception->getCode() === '55006') {
                    $this->adminConnection?->select(
                        'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
                        [$databaseName],
                    );

                    try {
                        $adminPdo->exec('DROP DATABASE IF EXISTS ' . $this->quoteIdentifier($databaseName));

                        continue;
                    } catch (Throwable $retryException) {
                        $exception = $retryException;
                    }
                }

                $failures[] = $databaseName . ' (' . $exception::class . ': ' . $exception->getMessage() . ')';
                $remainingDatabaseNames[] = $databaseName;
            }
        }

        $this->createdDatabaseNames = $remainingDatabaseNames;

        if ($failures !== []) {
            throw new RuntimeException(
                'Could not clean up disposable tenancy test database(s): ' . implode(', ', $failures) . '.'
            );
        }

        $managedRoleNames = array_values(array_unique([
            ...$this->createdTenantOwnerRoleNames,
            ...$this->createdTenantRoleNames,
        ]));

        if ($managedRoleNames === []) {
            return;
        }

        if (!is_string($this->createdTestProvisionerRoleName)) {
            throw new RuntimeException('The guarded test provisioner role is unavailable for membership cleanup.');
        }

        $provisionerConnectionName = config('tenancy.database.provisioner_connection');
        $provisionerPdo = is_string($provisionerConnectionName)
            ? DB::connection($provisionerConnectionName)->getPdo()
            : null;
        if (!$provisionerPdo instanceof PDO) {
            throw new RuntimeException('The guarded PostgreSQL roles are unavailable for membership cleanup.');
        }

        $memberships = $adminConnection->table('pg_auth_members as membership')
            ->join('pg_roles as granted_role', 'granted_role.oid', '=', 'membership.roleid')
            ->join('pg_roles as member_role', 'member_role.oid', '=', 'membership.member')
            ->join('pg_roles as grantor_role', 'grantor_role.oid', '=', 'membership.grantor')
            ->where(function ($query) use ($managedRoleNames): void {
                $query->whereIn('granted_role.rolname', $managedRoleNames)
                    ->orWhereIn('member_role.rolname', $managedRoleNames);
            })
            ->select([
                'granted_role.rolname as granted_role_name',
                'member_role.rolname as member_role_name',
                'grantor_role.rolname as grantor_name',
                'grantor_role.oid as grantor_id',
                'membership.admin_option',
                'membership.inherit_option',
                'membership.set_option',
            ])
            ->get();

        $allowedRelatedRoles = [...$managedRoleNames, $this->createdTestProvisionerRoleName];
        $provisionerMemberships = [];

        foreach ($memberships as $membership) {
            $grantorName = $membership->grantor_name;
            $grantedRoleName = $membership->granted_role_name;
            $memberRoleName = $membership->member_role_name;
            $isBootstrapProvisionerMembership = (int)$membership->grantor_id === self::POSTGRESQL_BOOTSTRAP_SUPERUSER_OID
                && $grantedRoleName !== null
                && in_array($grantedRoleName, $managedRoleNames, true)
                && $memberRoleName === $this->createdTestProvisionerRoleName
                && $membership->admin_option
                && !$membership->inherit_option
                && !$membership->set_option;

            if (!is_string($grantorName)
                || !is_string($grantedRoleName)
                || !is_string($memberRoleName)
                || !in_array($grantedRoleName, $allowedRelatedRoles, true)
                || !in_array($memberRoleName, $allowedRelatedRoles, true)
                || (!in_array($grantedRoleName, $managedRoleNames, true)
                    && !in_array($memberRoleName, $managedRoleNames, true))
                || (!$isBootstrapProvisionerMembership
                    && ($grantorName !== $this->createdTestProvisionerRoleName || $membership->admin_option))) {
                throw new RuntimeException('Refusing to clean up an unrecognized or delegable tenant role membership.');
            }

            if ($isBootstrapProvisionerMembership) {
                continue;
            }

            $provisionerMemberships[] = [$grantedRoleName, $memberRoleName];
        }

        foreach ($provisionerMemberships as [$grantedRoleName, $memberRoleName]) {
            $provisionerPdo->exec(
                'REVOKE ' . $this->quoteSqlIdentifier($grantedRoleName)
                . ' FROM ' . $this->quoteSqlIdentifier($memberRoleName)
                . ' GRANTED BY CURRENT_USER'
            );
        }

    }

    private function quoteSqlIdentifier(string $identifier): string
    {
        if (mb_strlen($identifier) > 63 || !preg_match('/\A[A-Za-z0-9_-]+\z/', $identifier)) {
            throw new RuntimeException('Refusing a PostgreSQL identifier outside the approved generated format.');
        }

        return '"' . $identifier . '"';
    }

    private function dropTestProvisionerRole(): void
    {
        if ($this->createdTestProvisionerRoleName === null
            || $this->adminConnection === null
            || $this->originalProvisionerConnectionName === null) {
            return;
        }

        DB::purge($this->originalProvisionerConnectionName);
        $quotedRoleName = $this->quoteTestProvisionerRoleName($this->createdTestProvisionerRoleName);
        $revokeFailure = null;

        try {
            $this->adminConnection->statement('REVOKE CONNECT ON DATABASE "testing" FROM ' . $quotedRoleName);
        } catch (Throwable $exception) {
            $revokeFailure = $exception;
        }

        try {
            $this->adminConnection->statement('DROP ROLE IF EXISTS ' . $quotedRoleName);
            $this->createdTestProvisionerRoleName = null;
            $this->createdTestProvisionerPassword = null;
        } catch (Throwable $dropFailure) {
            throw new RuntimeException(
                'Could not clean up the disposable PostgreSQL provisioner role.',
                previous: $revokeFailure ?? $dropFailure,
            );
        }

        if ($revokeFailure !== null) {
            report($revokeFailure);
        }
    }

    private function restoreTestProvisionerConfiguration(): void
    {
        $connectionName = $this->originalProvisionerConnectionName;

        if ($connectionName === null || $this->originalProvisionerConfiguration === null) {
            return;
        }

        DB::purge($connectionName);
        config(['database.connections.' . $connectionName => $this->originalProvisionerConfiguration]);
        $this->originalProvisionerConfiguration = null;
        $this->originalProvisionerConnectionName = null;
    }

    private function cleanupDisposableRoles(): void
    {
        $adminPdo = $this->adminConnection?->getPdo();

        if (!$adminPdo instanceof PDO) {
            throw new RuntimeException('The guarded PostgreSQL test connection is unavailable for role cleanup.');
        }

        $remainingOwnerRoleNames = [];

        foreach ($this->createdTenantOwnerRoleNames as $ownerRoleName) {
            try {
                $adminPdo->exec('DROP ROLE IF EXISTS ' . $this->quoteTenantOwnerRoleName($ownerRoleName));
            } catch (Throwable $exception) {
                $remainingOwnerRoleNames[] = $ownerRoleName . ' (' . $exception::class . ': ' . $exception->getMessage() . ')';
            }
        }

        if ($remainingOwnerRoleNames !== []) {
            throw new RuntimeException(
                'Could not clean up disposable PostgreSQL owner role(s): ' . implode(', ', $remainingOwnerRoleNames) . '.'
            );
        }

        $this->createdTenantOwnerRoleNames = [];

        $remainingRoleNames = [];

        foreach ($this->createdTenantRoleNames as $username) {
            try {
                $adminPdo->exec('DROP ROLE IF EXISTS ' . $this->quoteRoleName($username));
            } catch (Throwable) {
                $remainingRoleNames[] = $username;
            }
        }

        $this->createdTenantRoleNames = $remainingRoleNames;

        if ($remainingRoleNames !== []) {
            throw new RuntimeException(
                'Could not clean up disposable tenancy test role(s): ' . implode(', ', $remainingRoleNames) . '.'
            );
        }
    }

    private function quoteTenantOwnerRoleName(string $ownerRoleName): string
    {
        if (preg_match(self::TENANT_OWNER_ROLE_NAME_PATTERN, $ownerRoleName) !== 1) {
            throw new RuntimeException('Refusing PostgreSQL owner role operation outside the generated tenant format.');
        }

        return '"' . $ownerRoleName . '"';
    }

    private function quoteRoleName(string $username): string
    {
        if (preg_match(self::TENANT_ROLE_NAME_PATTERN, $username) !== 1) {
            throw new RuntimeException('Refusing PostgreSQL role operation outside the generated tenant format.');
        }

        return '"' . $username . '"';
    }
}
