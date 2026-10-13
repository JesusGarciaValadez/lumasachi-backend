<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../Support/Tenancy/DisposablePostgresDatabases.php';

test('tenant database credentials isolate company data and cannot connect to another tenant database', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenants = [Tenant::factory()->make(), Tenant::factory()->make()];
    $tenantConnections = [];
    $companyConnections = [];
    $centralConnectionName = null;
    $originalCentralConnectionName = config('tenancy.database.central_connection');
    $originalCentralConnectionConfiguration = null;

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        [$centralDatabaseName] = $disposableDatabases->createPair();
        $centralDatabaseConfiguration = (new ConfigurationUrlParser)->parseConfiguration(DB::connection()->getConfig());
        unset($centralDatabaseConfiguration['url']);
        $centralDatabaseConfiguration['database'] = $centralDatabaseName;
        $centralConnectionName = 'central_isolation_' . $centralDatabaseName;
        $originalCentralConnectionConfiguration = config('database.connections.' . $centralConnectionName);
        $centralDatabaseConfiguration['name'] = $centralConnectionName;
        config([
            'database.connections.' . $centralConnectionName => $centralDatabaseConfiguration,
            'tenancy.database.central_connection' => $centralConnectionName,
        ]);
        $centralConnection = DB::connection($centralConnectionName);

        if (!$centralConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build the disposable central database connection.');
        }

        $tenantConnections[] = $centralConnection;
        $centralMigrationExitCode = Artisan::call('tenancy:migrate-central');

        if ($centralMigrationExitCode !== 0) {
            throw new RuntimeException('The central ACL migration failed: ' . Artisan::output());
        }

        foreach ($tenants as $tenant) {
            $tenant->database()->makeCredentials();
            $disposableDatabases->registerTenantProvisioningAttempt($tenant);
            $databaseManager = $tenant->database()->manager();
            $databaseManager->createDatabase($tenant);
            $disposableDatabases->registerProvisionedTenant($tenant);
        }

        foreach ($tenants as $tenant) {
            $connectionConfiguration = $tenant->database()->connection();
            $connectionConfiguration['name'] = 'tenant_isolation_' . $tenant->getTenantKey();
            $tenantConnection = DB::build($connectionConfiguration);

            if (!$tenantConnection instanceof Connection) {
                throw new RuntimeException('Laravel did not build a tenant database connection.');
            }

            $tenantConnections[] = $tenantConnection;
            $companyConnections[] = $tenantConnection;
            $tenantConnection->statement('CREATE TABLE tenancy_test_users (id integer PRIMARY KEY, email text NOT NULL, password_marker text NOT NULL)');
            $tenantConnection->statement('CREATE TABLE tenancy_test_orders (id integer PRIMARY KEY, order_marker text NOT NULL)');
        }

        $sharedEmail = 'independent-account@example.test';
        $companyConnections[0]->insert(
            'INSERT INTO tenancy_test_users (id, email, password_marker) VALUES (?, ?, ?)',
            [17, $sharedEmail, 'company-a-password-marker'],
        );
        $companyConnections[1]->insert(
            'INSERT INTO tenancy_test_users (id, email, password_marker) VALUES (?, ?, ?)',
            [17, $sharedEmail, 'company-b-password-marker'],
        );
        $companyConnections[0]->insert(
            'INSERT INTO tenancy_test_orders (id, order_marker) VALUES (?, ?)',
            [23, 'company-a-order-marker'],
        );
        $companyConnections[1]->insert(
            'INSERT INTO tenancy_test_orders (id, order_marker) VALUES (?, ?)',
            [23, 'company-b-order-marker'],
        );

        $firstTenantUser = $companyConnections[0]->table('tenancy_test_users')->first();
        $secondTenantUser = $companyConnections[1]->table('tenancy_test_users')->first();
        $firstTenantOrder = $companyConnections[0]->table('tenancy_test_orders')->first();
        $secondTenantOrder = $companyConnections[1]->table('tenancy_test_orders')->first();

        if ($firstTenantUser === null || $secondTenantUser === null || $firstTenantOrder === null || $secondTenantOrder === null) {
            throw new RuntimeException('The two disposable tenant databases must contain their isolation fixtures.');
        }

        expect($firstTenantUser->id)->toBe($secondTenantUser->id)
            ->and($firstTenantUser->email)->toBe($secondTenantUser->email)
            ->and($firstTenantUser->password_marker)->not->toBe($secondTenantUser->password_marker)
            ->and($firstTenantOrder->id)->toBe($secondTenantOrder->id)
            ->and($firstTenantOrder->order_marker)->not->toBe($secondTenantOrder->order_marker);

        $tenantRole = DB::connection(config('tenancy.database.provisioner_connection'))
            ->table('pg_roles')
            ->select(['rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolinherit', 'rolbypassrls', 'rolreplication'])
            ->where('rolname', $tenants[0]->database()->getUsername())
            ->first();

        if ($tenantRole === null) {
            throw new RuntimeException('The provisioned tenant database role must exist.');
        }

        $tenantOwnerRoleName = (string)config('tenancy.database.owner_role_prefix')
            . str_replace('-', '', (string)$tenants[0]->getTenantKey());
        $provisionerConnection = DB::connection(config('tenancy.database.provisioner_connection'));
        $tenantRoleUsername = (string)$tenants[0]->database()->getUsername();
        $tenantRuntimeBootstrapMembership = $provisionerConnection->selectOne(
            'SELECT grantor_role.oid AS grantor_id, grantor_role.rolsuper AS grantor_is_superuser, '
            . 'membership.admin_option, membership.inherit_option, membership.set_option '
            . 'FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'JOIN pg_roles AS grantor_role ON grantor_role.oid = membership.grantor '
            . 'WHERE granted_role.rolname = ? AND member_role.rolname = current_user AND grantor_role.oid = 10',
            [$tenantRoleUsername],
        );
        $tenantRuntimeRoleHasUnsafeMembership = $provisionerConnection->selectOne(
            'SELECT EXISTS ('
            . 'SELECT 1 FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'JOIN pg_roles AS grantor_role ON grantor_role.oid = membership.grantor '
            . 'WHERE (granted_role.rolname = ? OR member_role.rolname = ?) '
            . 'AND NOT (granted_role.rolname = ? AND member_role.rolname = current_user '
            . 'AND grantor_role.oid = 10 AND membership.admin_option '
            . 'AND NOT membership.inherit_option AND NOT membership.set_option)'
            . ') AS has_unsafe_membership',
            [$tenantRoleUsername, $tenantRoleUsername, $tenantRoleUsername],
        );
        $databaseOwner = $provisionerConnection->table('pg_database')
            ->selectRaw('pg_get_userbyid(datdba) AS owner')
            ->where('datname', $tenants[0]->database()->getName())
            ->first();

        if ($databaseOwner === null) {
            throw new RuntimeException('The provisioned tenant database must have a database owner.');
        }

        expect($tenantRole->rolsuper)->toBeFalse()
            ->and($tenantRole->rolcreatedb)->toBeFalse()
            ->and($tenantRole->rolcreaterole)->toBeFalse()
            ->and($tenantRole->rolinherit)->toBeFalse()
            ->and($tenantRole->rolbypassrls)->toBeFalse()
            ->and($tenantRole->rolreplication)->toBeFalse()
            ->and($tenantRuntimeBootstrapMembership)->not->toBeNull()
            ->and((int)$tenantRuntimeBootstrapMembership?->grantor_id)->toBe(10)
            ->and($tenantRuntimeBootstrapMembership?->grantor_is_superuser)->toBeTrue()
            ->and($tenantRuntimeBootstrapMembership?->admin_option)->toBeTrue()
            ->and($tenantRuntimeBootstrapMembership?->inherit_option)->toBeFalse()
            ->and($tenantRuntimeBootstrapMembership?->set_option)->toBeFalse()
            ->and($tenantRuntimeRoleHasUnsafeMembership?->has_unsafe_membership)->toBeFalse()
            ->and($databaseOwner->owner)->toBe($tenantOwnerRoleName)
            ->and($databaseOwner->owner)->not->toBe($tenants[0]->database()->getUsername());

        $tenantDatabaseName = $tenants[0]->database()->getName();
        $tenantOwnerRole = DB::connection(config('tenancy.database.provisioner_connection'))
            ->table('pg_roles')
            ->select(['rolcanlogin', 'rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolinherit', 'rolbypassrls', 'rolreplication'])
            ->where('rolname', $tenantOwnerRoleName)
            ->first();

        if ($tenantOwnerRole === null) {
            throw new RuntimeException('The tenant database must have its restricted, non-login owner role.');
        }

        $provisionerCanSetTenantOwnerRole = $provisionerConnection->selectOne(
            'SELECT EXISTS ('
            . 'SELECT 1 FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'WHERE granted_role.rolname = ? AND member_role.rolname = current_user AND membership.set_option'
            . ') AS can_set_role',
            [$tenantOwnerRoleName],
        );

        expect($tenantOwnerRole->rolcanlogin)->toBeFalse()
            ->and($tenantOwnerRole->rolsuper)->toBeFalse()
            ->and($tenantOwnerRole->rolcreatedb)->toBeTrue()
            ->and($tenantOwnerRole->rolcreaterole)->toBeFalse()
            ->and($tenantOwnerRole->rolinherit)->toBeFalse()
            ->and($tenantOwnerRole->rolbypassrls)->toBeFalse()
            ->and($tenantOwnerRole->rolreplication)->toBeFalse()
            ->and($provisionerCanSetTenantOwnerRole?->can_set_role)->toBeFalse();

        $companyACredentialsAgainstCompanyB = $tenants[0]->database()->connection();
        $companyACredentialsAgainstCompanyB['database'] = $tenants[1]->database()->getName();
        $companyACredentialsAgainstCompanyB['name'] = 'tenant_a_forbidden_on_b';
        $crossTenantConnection = DB::build($companyACredentialsAgainstCompanyB);

        if (!$crossTenantConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build the cross-tenant denial connection.');
        }

        $tenantConnections[] = $crossTenantConnection;

        expect(fn() => $crossTenantConnection->selectOne('SELECT current_database()'))
            ->toThrow(QueryException::class);

        $tenantACredentialsAgainstCentral = $tenants[0]->database()->connection();
        $tenantACredentialsAgainstCentral['database'] = $centralDatabaseName;
        $tenantACredentialsAgainstCentral['name'] = 'tenant_a_forbidden_on_central';
        $centralForbiddenConnection = DB::build($tenantACredentialsAgainstCentral);

        if (!$centralForbiddenConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build the central denial connection.');
        }

        $tenantConnections[] = $centralForbiddenConnection;

        expect(fn() => $centralForbiddenConnection->selectOne('SELECT current_database()'))
            ->toThrow(QueryException::class);
    } finally {
        foreach ($tenantConnections as $tenantConnection) {
            $tenantConnection->disconnect();
        }

        if (is_string($centralConnectionName)) {
            DB::purge($centralConnectionName);
            config([
                'database.connections.' . $centralConnectionName => $originalCentralConnectionConfiguration,
                'tenancy.database.central_connection' => $originalCentralConnectionName,
            ]);
        }

        $disposableDatabases->cleanup();

        foreach ($tenants as $tenant) {
            $databaseName = $tenant->database()->getName();
            $username = $tenant->database()->getUsername();

            if (!is_string($databaseName) || !is_string($username)) {
                throw new RuntimeException('The cleaned-up test tenant must retain its generated resource names.');
            }

            $ownerRoleName = (string)config('tenancy.database.owner_role_prefix')
                . str_replace('-', '', (string)$tenant->getTenantKey());
            $tenantResourcesRemain = DB::connection()->table('pg_database')
                    ->where('datname', $databaseName)
                    ->exists()
                || DB::connection()->table('pg_roles')->whereIn('rolname', [$username, $ownerRoleName])->exists();

            expect($tenantResourcesRemain)->toBeFalse();
        }
    }
});

test('tenant lifecycle operations hold the same exclusive per-tenant advisory lock', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenant = Tenant::factory()->make();
    $observerConnection = null;
    $observerConnectionName = 'tenant_lifecycle_observer_' . $tenant->getTenantKey();

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);

        $provisionerConnectionName = config('tenancy.database.provisioner_connection');
        $observerConfiguration = (new ConfigurationUrlParser)->parseConfiguration(
            DB::connection($provisionerConnectionName)->getConfig(),
        );
        unset($observerConfiguration['url']);
        $observerConfiguration['name'] = $observerConnectionName;
        $observerConnection = DB::build($observerConfiguration);

        if (!$observerConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build the tenant lifecycle lock observer connection.');
        }

        $lockKey = 'lumasachi:tenant-lifecycle:' . $tenant->getTenantKey();
        $observedLocks = 0;
        $observerFoundLockHeld = [];
        $observer = $observerConnection;

        DB::listen(function (QueryExecuted $query) use ($lockKey, $observer, &$observedLocks, &$observerFoundLockHeld): void {
            $sql = mb_strtolower($query->sql);

            if (!str_contains($sql, 'pg_advisory_lock(') || str_contains($sql, 'pg_try_advisory_lock(')) {
                return;
            }

            $observedLocks++;
            $lockProbe = $observer->selectOne(
                'SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired',
                [$lockKey],
            );
            $observerFoundLockHeld[] = $lockProbe !== null && !$lockProbe->acquired;
        });

        $manager = $tenant->database()->manager();
        expect($manager->createDatabase($tenant))->toBeTrue();
        $disposableDatabases->registerProvisionedTenant($tenant);

        expect($manager->deleteDatabase($tenant))->toBeTrue()
            ->and($observedLocks)->toBe(2)
            ->and($observerFoundLockHeld)->toBe([true, true]);

        $releasedLock = $observerConnection->selectOne(
            'SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired',
            [$lockKey],
        );

        expect($releasedLock?->acquired)->toBeTrue();

        $unlock = $observerConnection->selectOne(
            'SELECT pg_advisory_unlock(hashtextextended(?, 0)) AS released',
            [$lockKey],
        );

        expect($unlock?->released)->toBeTrue();
    } finally {
        DB::purge($observerConnectionName);
        $disposableDatabases->cleanup();
    }
});

test('a retry revokes temporary owner-role set access left by an interrupted lifecycle operation', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenant = Tenant::factory()->make();

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);

        $manager = $tenant->database()->manager();
        $manager->createDatabase($tenant);
        $disposableDatabases->registerProvisionedTenant($tenant);

        $ownerRoleName = (string)config('tenancy.database.owner_role_prefix')
            . str_replace('-', '', (string)$tenant->getTenantKey());
        $provisionerConnection = DB::connection(config('tenancy.database.provisioner_connection'));
        $provisionerIdentity = $provisionerConnection->selectOne('SELECT session_user, current_user');

        expect($provisionerIdentity?->session_user)->toBe($provisionerConnection->getConfig('username'))
            ->and($provisionerIdentity?->current_user)->toBe($provisionerConnection->getConfig('username'));

        $provisionerConnection->statement(
            'GRANT "' . $ownerRoleName . '" TO CURRENT_USER WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );

        expect($provisionerConnection->selectOne(
            'SELECT EXISTS ('
            . 'SELECT 1 FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'JOIN pg_roles AS grantor_role ON grantor_role.oid = membership.grantor '
            . 'WHERE granted_role.rolname = ? '
            . 'AND member_role.rolname = current_user '
            . 'AND grantor_role.rolname = current_user '
            . 'AND membership.set_option'
            . ') AS granted',
            [$ownerRoleName],
        )?->granted)->toBeTrue();

        expect(fn() => $manager->createDatabase($tenant))
            ->toThrow(TenantDatabaseAlreadyExistsException::class);

        $remainingTemporaryMembership = $provisionerConnection->selectOne(
            'SELECT granted_role.rolname AS granted_role '
            . 'FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'JOIN pg_roles AS grantor_role ON grantor_role.oid = membership.grantor '
            . 'WHERE granted_role.rolname = ? '
            . 'AND member_role.rolname = current_user '
            . 'AND grantor_role.rolname = current_user',
            [$ownerRoleName],
        );

        $bootstrapMembership = $provisionerConnection->selectOne(
            'SELECT grantor_role.oid AS grantor_id, grantor_role.rolsuper AS grantor_is_superuser, '
            . 'membership.admin_option, membership.inherit_option, membership.set_option '
            . 'FROM pg_auth_members AS membership '
            . 'JOIN pg_roles AS granted_role ON granted_role.oid = membership.roleid '
            . 'JOIN pg_roles AS member_role ON member_role.oid = membership.member '
            . 'JOIN pg_roles AS grantor_role ON grantor_role.oid = membership.grantor '
            . 'WHERE granted_role.rolname = ? '
            . 'AND member_role.rolname = current_user '
            . 'AND grantor_role.oid = 10',
            [$ownerRoleName],
        );

        expect($remainingTemporaryMembership)->toBeNull()
            ->and($bootstrapMembership)->not->toBeNull()
            ->and((int)$bootstrapMembership?->grantor_id)->toBe(10)
            ->and($bootstrapMembership?->grantor_is_superuser)->toBeTrue()
            ->and($bootstrapMembership?->admin_option)->toBeTrue()
            ->and($bootstrapMembership?->inherit_option)->toBeFalse()
            ->and($bootstrapMembership?->set_option)->toBeFalse();
    } finally {
        $disposableDatabases->cleanup();
    }
});

test('a retry restores isolated database access after interruption immediately after database creation', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenant = Tenant::factory()->make();
    $tenantConnections = [];
    $tenantConnectionName = 'tenant_acl_recovery_' . $tenant->getTenantKey();
    $tenantAdminConnectionName = 'tenant_acl_recovery_admin_' . $tenant->getTenantKey();
    $crossTenantConnectionName = 'tenant_acl_recovery_cross_' . $tenant->getTenantKey();
    $quoteIdentifier = static fn(string $identifier): string => '"' . str_replace('"', '""', $identifier) . '"';

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);

        $manager = $tenant->database()->manager();
        expect($manager->createDatabase($tenant))->toBeTrue();
        $disposableDatabases->registerProvisionedTenant($tenant);

        $databaseName = $tenant->database()->getName();

        if (!is_string($databaseName)) {
            throw new RuntimeException('The recovery test tenant must have a generated database name.');
        }

        $username = $tenant->database()->getUsername();

        if (!is_string($username)) {
            throw new RuntimeException('The recovery test tenant must have a generated database username.');
        }

        $ownerRole = (string)config('tenancy.database.owner_role_prefix')
            . str_replace('-', '', (string)$tenant->getTenantKey());
        $provisionerConnection = DB::connection(config('tenancy.database.provisioner_connection'));
        $provisionerUsername = (string)$provisionerConnection->getConfig('username');

        $provisionerConnection->statement(
            'GRANT ' . $quoteIdentifier($ownerRole)
            . ' TO CURRENT_USER WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );
        $provisionerConnection->statement('SET ROLE ' . $quoteIdentifier($ownerRole));

        try {
            $provisionerConnection->statement(
                'GRANT CONNECT ON DATABASE ' . $quoteIdentifier($databaseName) . ' TO PUBLIC'
            );
            $provisionerConnection->statement(
                'REVOKE ALL ON DATABASE ' . $quoteIdentifier($databaseName)
                . ' FROM ' . $quoteIdentifier($username)
            );
            $provisionerConnection->statement(
                'REVOKE ALL ON DATABASE ' . $quoteIdentifier($databaseName)
                . ' FROM ' . $quoteIdentifier($provisionerUsername)
            );
        } finally {
            $provisionerConnection->statement('RESET ROLE');
        }

        $tenantAdminConfiguration = (new ConfigurationUrlParser)->parseConfiguration($provisionerConnection->getConfig());
        unset($tenantAdminConfiguration['url']);
        $tenantAdminConfiguration['database'] = $databaseName;
        $tenantAdminConfiguration['name'] = $tenantAdminConnectionName;
        $tenantAdminConnection = DB::build($tenantAdminConfiguration);

        if (!$tenantAdminConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build the simulated interrupted tenant connection.');
        }

        $tenantAdminConnection->statement('SET ROLE ' . $quoteIdentifier($ownerRole));

        try {
            $tenantAdminConnection->statement('GRANT CREATE ON SCHEMA public TO PUBLIC');
            $tenantAdminConnection->statement(
                'REVOKE ALL ON SCHEMA public FROM ' . $quoteIdentifier($username)
            );
        } finally {
            $tenantAdminConnection->statement('RESET ROLE');
            DB::purge($tenantAdminConnectionName);
        }

        expect(fn() => $manager->createDatabase($tenant))
            ->toThrow(TenantDatabaseAlreadyExistsException::class);

        $tenantConfiguration = $tenant->database()->connection();
        $tenantConfiguration['name'] = $tenantConnectionName;
        $tenantConnection = DB::build($tenantConfiguration);

        if (!$tenantConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build the recovered tenant runtime connection.');
        }

        $tenantConnections[] = $tenantConnection;
        expect($tenantConnection->selectOne('SELECT current_database() AS database_name')->database_name)
            ->toBe($databaseName);
        $tenantConnection->statement('CREATE TABLE recovery_probe (id integer PRIMARY KEY)');

        $otherTenant = Tenant::factory()->make();
        $otherTenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($otherTenant);
        $otherTenant->database()->manager()->createDatabase($otherTenant);
        $disposableDatabases->registerProvisionedTenant($otherTenant);

        $crossTenantConfiguration = $otherTenant->database()->connection();
        $crossTenantConfiguration['database'] = $databaseName;
        $crossTenantConfiguration['name'] = $crossTenantConnectionName;
        $crossTenantConnection = DB::build($crossTenantConfiguration);

        if (!$crossTenantConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build the cross-tenant recovery test connection.');
        }

        $tenantConnections[] = $crossTenantConnection;
        expect(fn() => $crossTenantConnection->selectOne('SELECT current_database()'))
            ->toThrow(QueryException::class);
    } finally {
        DB::purge($tenantConnectionName);
        DB::purge($tenantAdminConnectionName);
        DB::purge($crossTenantConnectionName);
        $disposableDatabases->cleanup();
    }
});

test('database recovery rejects owner and runtime roles that belong to other roles', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenants = [Tenant::factory()->make(), Tenant::factory()->make()];

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();

        foreach ($tenants as $tenant) {
            $tenant->database()->makeCredentials();
            $disposableDatabases->registerTenantProvisioningAttempt($tenant);
            $tenant->database()->manager()->createDatabase($tenant);
            $disposableDatabases->registerProvisionedTenant($tenant);
        }

        $firstOwnerRole = (string)config('tenancy.database.owner_role_prefix')
            . str_replace('-', '', (string)$tenants[0]->getTenantKey());
        $secondOwnerRole = (string)config('tenancy.database.owner_role_prefix')
            . str_replace('-', '', (string)$tenants[1]->getTenantKey());
        $firstRuntimeRole = $tenants[0]->database()->getUsername();
        $secondRuntimeRole = $tenants[1]->database()->getUsername();

        if (!is_string($firstRuntimeRole) || !is_string($secondRuntimeRole)) {
            throw new RuntimeException('The recovery test tenant must have a generated database username.');
        }

        $provisionerConnection = DB::connection(config('tenancy.database.provisioner_connection'));
        $quotedSecondOwnerRole = '"' . str_replace('"', '""', $secondOwnerRole) . '"';
        $quotedFirstOwnerRole = '"' . str_replace('"', '""', $firstOwnerRole) . '"';
        $quotedFirstRuntimeRole = '"' . str_replace('"', '""', $firstRuntimeRole) . '"';
        $quotedSecondRuntimeRole = '"' . str_replace('"', '""', $secondRuntimeRole) . '"';

        $provisionerConnection->statement(
            'GRANT ' . $quotedSecondOwnerRole
            . ' TO ' . $quotedFirstOwnerRole . ' WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );

        expect(fn() => $tenants[0]->database()->manager()->createDatabase($tenants[0]))
            ->toThrow(LogicException::class, 'owner role exists with unsafe PostgreSQL privileges');

        $provisionerConnection->statement(
            'REVOKE ' . $quotedSecondOwnerRole . ' FROM ' . $quotedFirstOwnerRole . ' GRANTED BY CURRENT_USER'
        );
        $provisionerConnection->statement(
            'GRANT ' . $quotedFirstOwnerRole
            . ' TO ' . $quotedSecondOwnerRole . ' WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );

        expect(fn() => $tenants[0]->database()->manager()->createDatabase($tenants[0]))
            ->toThrow(LogicException::class, 'owner role exists with unsafe PostgreSQL privileges');

        $provisionerConnection->statement(
            'REVOKE ' . $quotedFirstOwnerRole . ' FROM ' . $quotedSecondOwnerRole . ' GRANTED BY CURRENT_USER'
        );
        $provisionerConnection->statement(
            'GRANT ' . $quotedSecondOwnerRole
            . ' TO ' . $quotedFirstRuntimeRole . ' WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );

        expect(fn() => $tenants[0]->database()->manager()->createDatabase($tenants[0]))
            ->toThrow(LogicException::class, 'runtime role exists with unsafe PostgreSQL privileges');

        $provisionerConnection->statement(
            'REVOKE ' . $quotedSecondOwnerRole . ' FROM ' . $quotedFirstRuntimeRole . ' GRANTED BY CURRENT_USER'
        );
        $provisionerConnection->statement(
            'GRANT ' . $quotedFirstRuntimeRole
            . ' TO ' . $quotedSecondRuntimeRole . ' WITH INHERIT FALSE, SET TRUE, ADMIN FALSE GRANTED BY CURRENT_USER'
        );

        expect(fn() => $tenants[0]->database()->manager()->createDatabase($tenants[0]))
            ->toThrow(LogicException::class, 'runtime role exists with unsafe PostgreSQL privileges');
    } finally {
        $disposableDatabases->cleanup();
    }
});

test('tenant login creation failure after the role exists cleans up the uncertain role', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenant = Tenant::factory()->make();
    $failureInjected = false;

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);
        $username = $tenant->database()->getUsername();

        DB::listen(function (QueryExecuted $query) use ($username, &$failureInjected): void {
            if (!$failureInjected && str_starts_with($query->sql, 'CREATE ROLE "' . $username . '" LOGIN')) {
                $failureInjected = true;

                throw new RuntimeException('Simulated connection failure after PostgreSQL created the tenant login.');
            }
        });

        expect(fn() => $tenant->database()->manager()->createDatabase($tenant))
            ->toThrow(RuntimeException::class, 'Simulated connection failure');

        $tenantRole = DB::connection(config('tenancy.database.provisioner_connection'))
            ->table('pg_roles')
            ->where('rolname', $username)
            ->first();

        expect($failureInjected)->toBeTrue()
            ->and($tenantRole)->toBeNull();
    } finally {
        $disposableDatabases->cleanup();
    }
});

test('tenant deletion terminates existing sessions before dropping its database', function (): void {
    $disposableDatabases = new DisposablePostgresDatabases;
    $tenant = Tenant::factory()->make();
    $tenantConnectionName = 'tenant_deletion_active_session';

    try {
        $disposableDatabases->assertCanProvisionTestTenantDatabases();
        $tenant->database()->makeCredentials();
        $disposableDatabases->registerTenantProvisioningAttempt($tenant);
        $manager = $tenant->database()->manager();
        $manager->createDatabase($tenant);
        $disposableDatabases->registerProvisionedTenant($tenant);

        $connectionConfiguration = $tenant->database()->connection();
        $connectionConfiguration['name'] = $tenantConnectionName;
        $tenantConnection = DB::build($connectionConfiguration);

        if (!$tenantConnection instanceof Connection) {
            throw new RuntimeException('Laravel did not build a tenant deletion test connection.');
        }

        expect($tenantConnection->selectOne('SELECT current_database() AS database_name')->database_name)
            ->toBe($tenant->database()->getName());

        $tenantBackendPid = $tenantConnection->selectOne('SELECT pg_backend_pid() AS pid')->pid;
        $provisionerConnection = DB::connection(config('tenancy.database.provisioner_connection'));

        expect($manager->deleteDatabase($tenant))->toBeTrue()
            ->and($provisionerConnection->table('pg_stat_activity')->where('pid', $tenantBackendPid)->exists())->toBeFalse()
            ->and(fn() => $tenantConnection->selectOne('SELECT current_database()'))->toThrow(QueryException::class);

        $tenantRole = DB::connection(config('tenancy.database.provisioner_connection'))
            ->table('pg_roles')
            ->select('rolname')
            ->where('rolname', $tenant->database()->getUsername())
            ->first();

        $tenantDatabaseName = $tenant->database()->getName();

        if (!is_string($tenantDatabaseName)) {
            throw new RuntimeException('The disposable tenant database must have a generated name.');
        }

        expect($tenantRole)->toBeNull()
            ->and($manager->databaseExists($tenantDatabaseName))->toBeFalse();
    } finally {
        DB::purge($tenantConnectionName);
        $disposableDatabases->cleanup();
    }
});
