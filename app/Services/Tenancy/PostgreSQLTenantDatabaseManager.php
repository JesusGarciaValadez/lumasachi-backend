<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Stancl\Tenancy\Contracts\ManagesDatabaseUsers;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Stancl\Tenancy\Exceptions\TenantDatabaseUserAlreadyExistsException;
use Throwable;

final class PostgreSQLTenantDatabaseManager implements ManagesDatabaseUsers
{
    private const POSTGRESQL_BOOTSTRAP_SUPERUSER_OID = 10;

    private ?string $templateConnection = null;

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $databaseName = $this->databaseName($tenant);
        $databaseConfig = $tenant->database();
        $username = $this->username($databaseConfig->getUsername());
        $ownerRole = $this->ownerRoleName($tenant);

        return $this->withTenantLifecycleLock($tenant, function () use ($tenant, $databaseName, $databaseConfig, $username, $ownerRole): bool {
            $this->revokeTemporaryProvisioningRoleGrant($ownerRole);
            $this->revokeTemporaryProvisioningRoleGrant($username);

            if ($this->databaseExists($databaseName)) {
                $this->repairExistingTenantDatabase($tenant, $databaseName, $username, $ownerRole);

                throw new TenantDatabaseAlreadyExistsException($databaseName);
            }

            if ($this->userExists($username)) {
                throw new TenantDatabaseUserAlreadyExistsException($username);
            }

            $ownerRoleCreated = false;
            $usernameCreated = false;
            $usernameCreateSubmitted = false;
            $databaseCreated = false;
            $databaseCreateSubmitted = false;

            try {
                $ownerRoleCreated = $this->createOwnerRole($ownerRole);

                if (!$this->createUserRole($databaseConfig, static function () use (&$usernameCreateSubmitted): void {
                    $usernameCreateSubmitted = true;
                })) {
                    throw new TenantDatabaseUserAlreadyExistsException($username);
                }

                $usernameCreated = true;

                $connection = $this->provisionerConnection();
                $quotedDatabase = $this->quoteIdentifier($databaseName);
                $quotedOwnerRole = $this->quoteIdentifier($ownerRole);

                $this->grantOwnerRoleToProvisioner($connection, $ownerRole);
                $connection->statement('SET ROLE ' . $quotedOwnerRole);

                try {
                    $databaseCreateSubmitted = true;

                    if (!$connection->statement("CREATE DATABASE {$quotedDatabase} WITH TEMPLATE=template0 OWNER {$quotedOwnerRole}")) {
                        throw new RuntimeException('PostgreSQL did not create the tenant database.');
                    }

                    $databaseCreated = true;
                } finally {
                    $connection->statement('RESET ROLE');
                }

                $this->configureTenantDatabaseAccess($tenant, $databaseName, $username, $ownerRole, $connection);

                return true;
            } catch (Throwable $exception) {
                $databaseCleanupFailed = false;
                $databaseMayExist = $databaseCreated;

                if ($usernameCreateSubmitted
                    && !$usernameCreated
                    && !$this->isDuplicateRoleCreation($exception)) {
                    try {
                        $usernameCreated = $this->tenantRuntimeRoleExists($username);
                    } catch (Throwable $inspectionException) {
                        report($inspectionException);
                        $databaseCleanupFailed = true;
                    }
                }

                if (!$databaseMayExist && $databaseCreateSubmitted) {
                    try {
                        $databaseMayExist = $this->databaseExists($databaseName);
                    } catch (Throwable $inspectionException) {
                        report($inspectionException);
                        $databaseCleanupFailed = true;
                    }
                }

                if ($databaseMayExist) {
                    try {
                        $this->dropTenantDatabase($databaseName, $ownerRole);
                    } catch (Throwable $cleanupException) {
                        report($cleanupException);
                        $databaseCleanupFailed = true;
                    }
                }

                try {
                    $this->revokeOwnerRoleFromProvisioner($this->provisionerConnection(), $ownerRole);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                    $databaseCleanupFailed = true;
                }

                if ($usernameCreated) {
                    try {
                        if ($databaseCleanupFailed) {
                            $this->provisionerConnection()->statement('ALTER ROLE ' . $this->quoteIdentifier($username) . ' NOLOGIN');
                        } else {
                            $this->deleteUser($databaseConfig);
                        }
                    } catch (Throwable $cleanupException) {
                        report($cleanupException);
                    }
                }

                if ($ownerRoleCreated && !$databaseCleanupFailed) {
                    try {
                        $this->deleteOwnerRole($ownerRole);
                    } catch (Throwable $cleanupException) {
                        report($cleanupException);
                    }
                }

                throw $exception;
            }
        });
    }

    private function databaseName(TenantWithDatabase $tenant): string
    {
        $databaseConfig = $tenant->database();
        $expectedName = (string)config('tenancy.database.prefix')
            . $tenant->getTenantKey()
            . (string)config('tenancy.database.suffix');
        $databaseName = $databaseConfig->getName();

        $centralConnectionName = config('tenancy.database.central_connection');
        $centralConfiguration = is_string($centralConnectionName)
            ? config("database.connections.{$centralConnectionName}")
            : null;

        if (!is_array($centralConfiguration)) {
            throw new LogicException('The central PostgreSQL connection must be configured before tenant provisioning.');
        }

        $centralConfiguration = (new ConfigurationUrlParser)->parseConfiguration($centralConfiguration);
        $centralDatabaseName = $centralConfiguration['database'] ?? null;

        if (!is_string($centralDatabaseName) || $centralDatabaseName === '') {
            throw new LogicException('The central PostgreSQL connection must define its database name.');
        }

        if ($databaseName !== $expectedName || $databaseName === $centralDatabaseName) {
            throw new InvalidArgumentException('Tenant database names must be derived from the immutable tenant identifier.');
        }

        $this->assertDatabaseName($databaseName);

        return $databaseName;
    }

    private function assertDatabaseName(string $databaseName): void
    {
        $prefix = preg_quote((string)config('tenancy.database.prefix'), '/');
        $suffix = preg_quote((string)config('tenancy.database.suffix'), '/');
        $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

        if (mb_strlen($databaseName) > 63 || !preg_match('/\A' . $prefix . $uuid . $suffix . '\z/', $databaseName)) {
            throw new InvalidArgumentException('Tenant database names must use the configured prefix and a UUID identifier.');
        }
    }

    private function username(?string $username): string
    {
        if (!is_string($username) || !preg_match('/\A[A-Za-z0-9]{16}\z/', $username)) {
            throw new InvalidArgumentException('Tenant database usernames must be generated 16-character alphanumeric values.');
        }

        return $username;
    }

    private function ownerRoleName(TenantWithDatabase $tenant): string
    {
        $tenantKey = $tenant->getTenantKey();

        if (!is_string($tenantKey) || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $tenantKey)) {
            throw new InvalidArgumentException('Tenant database owner roles must be derived from a UUID tenant identifier.');
        }

        $ownerRole = (string)config('tenancy.database.owner_role_prefix') . str_replace('-', '', $tenantKey);

        if (mb_strlen($ownerRole) > 63 || !preg_match('/\A[A-Za-z0-9_-]+\z/', $ownerRole)) {
            throw new InvalidArgumentException('Tenant database owner role names must use the configured prefix and tenant UUID.');
        }

        return $ownerRole;
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $operation
     * @return TResult
     */
    private function withTenantLifecycleLock(TenantWithDatabase $tenant, callable $operation): mixed
    {
        $provisionerConnection = $this->provisionerConnection();
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($provisionerConnection->getConfig());
        unset($configuration['url']);

        $lockConnectionName = 'tenant_lifecycle_lock';
        $configuration['name'] = $lockConnectionName;
        $lockConnection = DB::build($configuration);

        if (!$lockConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build a tenant lifecycle lock connection.');
        }

        $lockKey = 'lumasachi:tenant-lifecycle:' . $tenant->getTenantKey();

        try {
            $lockConnection->selectOne('SELECT pg_advisory_lock(hashtextextended(?, 0))', [$lockKey]);
        } catch (Throwable $exception) {
            DB::purge($lockConnectionName);

            throw $exception;
        }

        try {
            return $operation();
        } finally {
            try {
                $lockReleased = $lockConnection->selectOne(
                    'SELECT pg_advisory_unlock(hashtextextended(?, 0)) AS released',
                    [$lockKey],
                );

                if ($lockReleased === null || !$lockReleased->released) {
                    throw new RuntimeException('The tenant lifecycle advisory lock was not held by its provisioner session.');
                }
            } catch (Throwable $exception) {
                report($exception);
            } finally {
                DB::purge($lockConnectionName);
            }
        }
    }

    private function provisionerConnection(): Connection
    {
        $connectionName = config('tenancy.database.provisioner_connection');

        if (!is_string($connectionName) || $connectionName === '') {
            throw new LogicException('The PostgreSQL tenant provisioner connection is not configured.');
        }

        $configuredConnection = config("database.connections.{$connectionName}");

        if (!is_array($configuredConnection) || ($configuredConnection['driver'] ?? null) !== 'pgsql') {
            throw new LogicException('The tenant provisioner must use a configured PostgreSQL connection.');
        }

        $configuredConnection = (new ConfigurationUrlParser)->parseConfiguration($configuredConnection);

        if (($configuredConnection['driver'] ?? null) !== 'pgsql') {
            throw new LogicException('The tenant provisioner URL must use PostgreSQL.');
        }

        if (!app()->environment('testing')) {
            $centralConnectionName = config('tenancy.database.central_connection');
            $centralConfiguration = config("database.connections.{$centralConnectionName}");

            if (!is_array($centralConfiguration) || ($centralConfiguration['driver'] ?? null) !== 'pgsql') {
                throw new LogicException('The central connection must use PostgreSQL.');
            }

            $centralUsername = $this->effectiveUsername($centralConfiguration);
            $provisionerUsername = $this->effectiveUsername($configuredConnection);

            if ($provisionerUsername === $centralUsername) {
                throw new LogicException('Configure a TENANCY_DB_PROVISIONER_URL or username separate from the central runtime role.');
            }
        }

        $connection = DB::connection($connectionName);
        $provisionerRole = $connection->selectOne(
            'SELECT rolcanlogin, rolsuper, rolcreatedb, rolcreaterole, rolreplication '
            . 'FROM pg_roles WHERE rolname = current_user'
        );

        if ($provisionerRole === null
            || !$provisionerRole->rolcanlogin
            || $provisionerRole->rolsuper
            || !$provisionerRole->rolcreatedb
            || !$provisionerRole->rolcreaterole
            || $provisionerRole->rolreplication) {
            throw new LogicException('The PostgreSQL tenant provisioner must be a CREATEDB/CREATEROLE login without SUPERUSER or REPLICATION.');
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function effectiveUsername(array $configuration): string
    {
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($configuration);
        $username = $configuration['username'] ?? null;

        if (!is_string($username) || $username === '') {
            throw new LogicException('A PostgreSQL connection username must be configured.');
        }

        return $username;
    }

    private function revokeTemporaryProvisioningRoleGrant(string $roleName): void
    {
        $connection = $this->provisionerConnection();
        $provisionerUsername = $this->provisionerUsername($connection);
        $grantExists = $connection->selectOne(
            'SELECT EXISTS ('
            . 'SELECT 1 FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'JOIN pg_roles AS grantor_role ON grantor_role.oid = membership.grantor '
            . 'WHERE granted_role.rolname = ? '
            . 'AND member_role.rolname = current_user '
            . 'AND grantor_role.rolname = current_user '
            . ') AS granted',
            [$roleName],
        );

        if ($grantExists !== null && $grantExists->granted) {
            $connection->statement(
                'REVOKE ' . $this->quoteIdentifier($roleName)
                . ' FROM ' . $this->quoteIdentifier($provisionerUsername)
                . ' GRANTED BY CURRENT_USER'
            );
        }
    }

    private function provisionerUsername(Connection $connection): string
    {
        return $this->effectiveUsername($connection->getConfig());
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/\A[A-Za-z0-9_-]{1,63}\z/', $identifier)) {
            throw new InvalidArgumentException('PostgreSQL identifiers must use only allowed generated characters.');
        }

        return '"' . $identifier . '"';
    }

    public function databaseExists(string $name): bool
    {
        $this->assertDatabaseName($name);

        return $this->provisionerConnection()
            ->table('pg_database')
            ->where('datname', $name)
            ->exists();
    }

    private function repairExistingTenantDatabase(
        TenantWithDatabase $tenant,
        string             $databaseName,
        string             $username,
        string             $ownerRole,
    ): void
    {
        $connection = $this->provisionerConnection();
        $databaseOwner = $connection->selectOne(
            'SELECT pg_get_userbyid(datdba) AS owner FROM pg_database WHERE datname = ?',
            [$databaseName],
        );

        if ($databaseOwner === null || $databaseOwner->owner !== $ownerRole) {
            throw new TenantDatabaseAlreadyExistsException($databaseName);
        }

        $this->createOwnerRole($ownerRole);

        if (!$this->tenantRuntimeRoleExists($username)) {
            throw new TenantDatabaseAlreadyExistsException($databaseName);
        }

        $this->configureTenantDatabaseAccess($tenant, $databaseName, $username, $ownerRole, $connection);
    }

    private function createOwnerRole(string $ownerRole): bool
    {
        $connection = $this->provisionerConnection();
        $existingRole = $connection->table('pg_roles')
            ->select(['rolcanlogin', 'rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolinherit', 'rolbypassrls', 'rolreplication'])
            ->where('rolname', $ownerRole)
            ->first();

        if ($existingRole !== null) {
            if ($existingRole->rolcanlogin
                || $existingRole->rolsuper
                || !$existingRole->rolcreatedb
                || $existingRole->rolcreaterole
                || $existingRole->rolinherit
                || $existingRole->rolbypassrls
                || $existingRole->rolreplication
                || $this->roleHasUnsafeMembership($ownerRole, allowProvisionerBootstrapMembership: true)) {
                throw new LogicException('The tenant database owner role exists with unsafe PostgreSQL privileges.');
            }

            return false;
        }

        $connection->statement(
            'CREATE ROLE ' . $this->quoteIdentifier($ownerRole)
            . ' NOLOGIN NOSUPERUSER CREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION'
        );

        return true;
    }

    private function roleHasUnsafeMembership(
        string $roleName,
        bool   $allowProvisionerBootstrapMembership = false,
    ): bool
    {
        $connection = $this->provisionerConnection();
        $provisionerUsername = $this->provisionerUsername($connection);
        $membership = $connection->selectOne(
            'SELECT EXISTS ('
            . 'SELECT 1 FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'JOIN pg_roles AS grantor_role ON grantor_role.oid = membership.grantor '
            . 'WHERE (granted_role.rolname = ? OR member_role.rolname = ?) '
            . 'AND NOT (? AND granted_role.rolname = ? AND member_role.rolname = ? '
            . 'AND grantor_role.oid = ' . self::POSTGRESQL_BOOTSTRAP_SUPERUSER_OID . ' AND membership.admin_option '
            . 'AND NOT membership.inherit_option AND NOT membership.set_option)'
            . ') AS has_unsafe_membership',
            [$roleName, $roleName, $allowProvisionerBootstrapMembership, $roleName, $provisionerUsername],
        );

        return (bool)$membership->has_unsafe_membership;
    }

    private function tenantRuntimeRoleExists(string $username): bool
    {
        $existingRole = $this->provisionerConnection()->table('pg_roles')
            ->select(['rolcanlogin', 'rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolinherit', 'rolbypassrls', 'rolreplication'])
            ->where('rolname', $username)
            ->first();

        if ($existingRole === null) {
            return false;
        }

        if (!$existingRole->rolcanlogin
            || $existingRole->rolsuper
            || $existingRole->rolcreatedb
            || $existingRole->rolcreaterole
            || $existingRole->rolinherit
            || $existingRole->rolbypassrls
            || $existingRole->rolreplication
            || $this->roleHasUnsafeMembership($username, allowProvisionerBootstrapMembership: true)) {
            throw new LogicException('The uncertain tenant runtime role exists with unsafe PostgreSQL privileges.');
        }

        return true;
    }

    private function configureTenantDatabaseAccess(
        TenantWithDatabase $tenant,
        string             $databaseName,
        string             $username,
        string             $ownerRole,
        Connection         $connection,
    ): void
    {
        $quotedDatabase = $this->quoteIdentifier($databaseName);
        $quotedUsername = $this->quoteIdentifier($username);
        $quotedOwnerRole = $this->quoteIdentifier($ownerRole);
        $quotedProvisionerUsername = $this->quoteIdentifier($this->provisionerUsername($connection));

        $this->grantOwnerRoleToProvisioner($connection, $ownerRole);

        try {
            $connection->statement('SET ROLE ' . $quotedOwnerRole);

            try {
                $connection->statement("REVOKE ALL ON DATABASE {$quotedDatabase} FROM PUBLIC");
                $connection->statement("REVOKE ALL ON DATABASE {$quotedDatabase} FROM {$quotedUsername}");
                $connection->statement("GRANT CONNECT, CREATE, TEMPORARY ON DATABASE {$quotedDatabase} TO {$quotedUsername}");
                $connection->statement("GRANT CONNECT ON DATABASE {$quotedDatabase} TO {$quotedProvisionerUsername}");
            } finally {
                $connection->statement('RESET ROLE');
            }

            $tenantAdminConnection = $this->tenantProvisionerConnection($databaseName, $tenant->getTenantKey());

            try {
                $tenantAdminConnection->statement('SET ROLE ' . $quotedOwnerRole);

                try {
                    $tenantAdminConnection->statement('REVOKE ALL ON SCHEMA public FROM PUBLIC');
                    $tenantAdminConnection->statement('REVOKE ALL ON SCHEMA public FROM ' . $quotedUsername);
                    $tenantAdminConnection->statement('GRANT USAGE, CREATE ON SCHEMA public TO ' . $quotedUsername);
                } finally {
                    $tenantAdminConnection->statement('RESET ROLE');
                }
            } finally {
                DB::purge($tenantAdminConnection->getName());
            }
        } finally {
            $this->revokeOwnerRoleFromProvisioner($connection, $ownerRole);
        }
    }

    private function grantOwnerRoleToProvisioner(Connection $connection, string $ownerRole): void
    {
        $currentRole = $connection->selectOne(
            'SELECT r.rolsuper FROM pg_roles r WHERE r.rolname = current_user'
        );

        if ($currentRole === null || $currentRole->rolsuper) {
            return;
        }

        $connection->statement(
            'GRANT ' . $this->quoteIdentifier($ownerRole)
            . ' TO ' . $this->quoteIdentifier($this->provisionerUsername($connection))
            . ' WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );
    }

    private function tenantProvisionerConnection(string $databaseName, string $tenantKey): Connection
    {
        $provisionerConnection = $this->provisionerConnection();
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($provisionerConnection->getConfig());
        unset($configuration['url']);
        $configuration['database'] = $databaseName;
        $configuration['name'] = 'tenant_acl';
        $tenantConnection = DB::build($configuration);

        if (!$tenantConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build a tenant database grant connection.');
        }

        return $tenantConnection;
    }

    private function revokeOwnerRoleFromProvisioner(Connection $connection, string $ownerRole): void
    {
        $currentRole = $connection->selectOne(
            'SELECT r.rolsuper FROM pg_roles r WHERE r.rolname = current_user'
        );

        if ($currentRole === null || $currentRole->rolsuper) {
            return;
        }

        $connection->statement(
            'REVOKE ' . $this->quoteIdentifier($ownerRole)
            . ' FROM ' . $this->quoteIdentifier($this->provisionerUsername($connection))
            . ' GRANTED BY CURRENT_USER'
        );
    }

    public function userExists(string $username): bool
    {
        $this->username($username);

        return $this->provisionerConnection()
            ->table('pg_roles')
            ->where('rolname', $username)
            ->exists();
    }

    private function createUserRole(DatabaseConfig $databaseConfig, ?Closure $onCreateSubmitted = null): bool
    {
        $username = $this->username($databaseConfig->getUsername());
        $password = $databaseConfig->getPassword();

        if (!is_string($password) || $password === '') {
            throw new LogicException('A tenant database password must be generated before provisioning.');
        }

        if ($this->userExists($username)) {
            return false;
        }

        $connection = $this->provisionerConnection();
        $passwordLiteral = $connection->getPdo()->quote($password);

        if (!is_string($passwordLiteral)) {
            throw new RuntimeException('The PostgreSQL connection could not safely quote a tenant password.');
        }

        $onCreateSubmitted?->__invoke();

        return $connection->statement(
            'CREATE ROLE ' . $this->quoteIdentifier($username)
            . ' LOGIN PASSWORD ' . $passwordLiteral
            . ' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION'
        );
    }

    private function isDuplicateRoleCreation(Throwable $exception): bool
    {
        return $exception instanceof QueryException
            && ($exception->errorInfo[0] ?? null) === '42710';
    }

    private function dropTenantDatabase(string $databaseName, string $ownerRole): void
    {
        $connection = $this->provisionerConnection();
        $databaseOwner = $connection->selectOne(
            'SELECT pg_get_userbyid(datdba) AS owner FROM pg_database WHERE datname = ?',
            [$databaseName],
        );

        if ($databaseOwner === null || $databaseOwner->owner !== $ownerRole) {
            throw new LogicException('Refusing to drop a tenant database not owned by its expected tenant owner role.');
        }

        $this->grantOwnerRoleToProvisioner($connection, $ownerRole);
        $roleWasSet = false;

        try {
            $connection->statement('SET ROLE ' . $this->quoteIdentifier($ownerRole));
            $roleWasSet = true;
            $connection->statement('DROP DATABASE ' . $this->quoteIdentifier($databaseName));
        } finally {
            try {
                if ($roleWasSet) {
                    $connection->statement('RESET ROLE');
                }
            } finally {
                $this->revokeOwnerRoleFromProvisioner($connection, $ownerRole);
            }
        }
    }

    public function deleteUser(DatabaseConfig $databaseConfig): bool
    {
        $username = $this->username($databaseConfig->getUsername());

        return $this->provisionerConnection()->statement(
            'DROP ROLE IF EXISTS ' . $this->quoteIdentifier($username)
        );
    }

    private function deleteOwnerRole(string $ownerRole): bool
    {
        $connection = $this->provisionerConnection();
        $provisionerUsername = $this->provisionerUsername($connection);
        $currentRole = $connection->selectOne(
            'SELECT r.rolsuper FROM pg_roles r WHERE r.rolname = current_user'
        );

        if ($currentRole !== null && !$currentRole->rolsuper) {
            $connection->statement(
                'REVOKE ' . $this->quoteIdentifier($ownerRole)
                . ' FROM ' . $this->quoteIdentifier($provisionerUsername)
            );
        }

        return $connection->statement('DROP ROLE IF EXISTS ' . $this->quoteIdentifier($ownerRole));
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $databaseName = $this->databaseName($tenant);
        $databaseConfig = $tenant->database();
        $ownerRole = $this->ownerRoleName($tenant);

        return $this->withTenantLifecycleLock($tenant, function () use ($databaseName, $databaseConfig, $ownerRole): bool {
            $username = $this->username($databaseConfig->getUsername());
            $this->revokeTemporaryProvisioningRoleGrant($ownerRole);
            $this->revokeTemporaryProvisioningRoleGrant($username);

            if ($this->databaseExists($databaseName)) {
                $connection = $this->provisionerConnection();
                $connection->statement('ALTER ROLE ' . $this->quoteIdentifier($username) . ' NOLOGIN');
                $this->terminateTenantSessions($connection, $databaseName, $username);
                $this->dropTenantDatabase($databaseName, $ownerRole);
            }

            $userDeleted = $this->deleteUser($databaseConfig);
            $ownerRoleDeleted = $this->deleteOwnerRole($ownerRole);

            return $userDeleted && $ownerRoleDeleted;
        });
    }

    private function terminateTenantSessions(Connection $connection, string $databaseName, string $username): void
    {
        $provisionerUsername = $this->provisionerUsername($connection);
        $quotedUsername = $this->quoteIdentifier($username);
        $quotedProvisionerUsername = $this->quoteIdentifier($provisionerUsername);

        $connection->statement(
            'GRANT ' . $quotedUsername . ' TO ' . $quotedProvisionerUsername
            . ' WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );

        try {
            $connection->statement('SET ROLE ' . $quotedUsername);

            try {
                $tenantSessionPids = $connection->select(
                    'SELECT pid FROM pg_stat_activity WHERE datname = ? AND usename = ? AND pid <> pg_backend_pid()',
                    [$databaseName, $username],
                );

                foreach ($tenantSessionPids as $tenantSession) {
                    $terminated = $connection->selectOne(
                        'SELECT pg_terminate_backend(?, 5000) AS terminated',
                        [$tenantSession->pid],
                    );

                    if ($terminated === null || !$terminated->terminated) {
                        throw new RuntimeException('PostgreSQL could not terminate an existing tenant database session.');
                    }
                }
            } finally {
                $connection->statement('RESET ROLE');
            }
        } finally {
            $connection->statement(
                'REVOKE ' . $quotedUsername . ' FROM ' . $quotedProvisionerUsername . ' GRANTED BY CURRENT_USER'
            );
        }
    }

    /**
     * @param array<string, mixed> $baseConfig
     * @return array<string, mixed>
     */
    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        $this->assertDatabaseName($databaseName);

        if ($this->templateConnection === null) {
            throw new LogicException('The tenant template connection must be set before building its configuration.');
        }

        $templateConfig = config("database.connections.{$this->templateConnection}");

        if (!is_array($templateConfig) || ($templateConfig['driver'] ?? null) !== 'pgsql') {
            throw new LogicException('The tenant template connection must use PostgreSQL.');
        }

        $tenantUsername = $baseConfig['username'] ?? null;
        $tenantPassword = $baseConfig['password'] ?? null;
        $baseConfig = (new ConfigurationUrlParser)->parseConfiguration($baseConfig);
        unset($baseConfig['url']);

        if (($baseConfig['driver'] ?? null) !== 'pgsql') {
            throw new LogicException('The tenant database connection URL must use PostgreSQL.');
        }

        $baseConfig['username'] = $this->username(is_string($tenantUsername) ? $tenantUsername : null);

        if (!is_string($tenantPassword) || $tenantPassword === '') {
            throw new LogicException('A tenant database password must be configured before building its connection.');
        }

        $baseConfig['password'] = $tenantPassword;
        $baseConfig['database'] = $databaseName;

        return $baseConfig;
    }

    public function setConnection(string $connection): void
    {
        if (!array_key_exists($connection, config('database.connections', []))) {
            throw new LogicException("The tenant template database connection [{$connection}] is not configured.");
        }

        $this->templateConnection = $connection;
    }

    public function createUser(DatabaseConfig $databaseConfig): bool
    {
        return $this->createUserRole($databaseConfig);
    }
}
