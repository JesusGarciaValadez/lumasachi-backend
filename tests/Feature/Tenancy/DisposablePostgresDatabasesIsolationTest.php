<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Support\Tenancy\DisposablePostgresDatabases;

require_once __DIR__ . '/../../Support/Tenancy/DisposablePostgresDatabases.php';

test('separate PostgreSQL tenant databases keep storage isolated', function (): void {
    $databases = new DisposablePostgresDatabases();

    try {
        [$firstDatabase, $secondDatabase] = $databases->createPair();
        $sharedEmail = 'shared-user@example.test';
        $firstCompanyData = [
            'user' => [
                'id' => 17,
                'email' => $sharedEmail,
                'credential_marker' => 'credential-company-a',
            ],
            'order' => [
                'id' => 23,
                'order_number' => 'A-ORDER-001',
                'description' => 'Company A fabricated order',
            ],
        ];
        $secondCompanyData = [
            'user' => [
                'id' => 17,
                'email' => $sharedEmail,
                'credential_marker' => 'credential-company-b',
            ],
            'order' => [
                'id' => 23,
                'order_number' => 'B-ORDER-001',
                'description' => 'Company B fabricated order',
            ],
        ];

        foreach ([[$firstDatabase, $firstCompanyData], [$secondDatabase, $secondCompanyData]] as [$database, $companyData]) {
            $databases->withDatabase($database, function (PDO $connection) use ($companyData): void {
                $connection->exec('create table tenancy_test_users (id bigint primary key, email text not null, credential_marker text not null)');
                $connection->exec('create table tenancy_test_orders (id bigint primary key, order_number text not null, description text not null)');

                $userStatement = $connection->prepare(
                    'insert into tenancy_test_users (id, email, credential_marker) values (?, ?, ?)'
                );
                $userStatement->execute([
                    $companyData['user']['id'],
                    $companyData['user']['email'],
                    $companyData['user']['credential_marker'],
                ]);

                $orderStatement = $connection->prepare(
                    'insert into tenancy_test_orders (id, order_number, description) values (?, ?, ?)'
                );
                $orderStatement->execute([
                    $companyData['order']['id'],
                    $companyData['order']['order_number'],
                    $companyData['order']['description'],
                ]);
            });
        }

        $readCompanyData = static function (PDO $connection): array {
            $userStatement = $connection->query(
                'select id, email, credential_marker from tenancy_test_users order by id'
            );
            $orderStatement = $connection->query(
                'select id, order_number, description from tenancy_test_orders order by id'
            );

            if (!$userStatement instanceof PDOStatement || !$orderStatement instanceof PDOStatement) {
                throw new RuntimeException('Could not read tenant records from a disposable test database.');
            }

            return [
                'users' => $userStatement->fetchAll(PDO::FETCH_ASSOC),
                'orders' => $orderStatement->fetchAll(PDO::FETCH_ASSOC),
            ];
        };

        $firstDatabaseData = $databases->withDatabase($firstDatabase, $readCompanyData);
        $secondDatabaseData = $databases->withDatabase($secondDatabase, $readCompanyData);

        expect($firstDatabaseData)->toBe([
            'users' => [[
                'id' => 17,
                'email' => $sharedEmail,
                'credential_marker' => 'credential-company-a',
            ]],
            'orders' => [[
                'id' => 23,
                'order_number' => 'A-ORDER-001',
                'description' => 'Company A fabricated order',
            ]],
        ])
            ->and($secondDatabaseData)->toBe([
                'users' => [[
                    'id' => 17,
                    'email' => $sharedEmail,
                    'credential_marker' => 'credential-company-b',
                ]],
                'orders' => [[
                    'id' => 23,
                    'order_number' => 'B-ORDER-001',
                    'description' => 'Company B fabricated order',
                ]],
            ])
            ->and($firstDatabaseData['users'][0]['id'])->toBe($secondDatabaseData['users'][0]['id'])
            ->and($firstDatabaseData['users'][0]['email'])->toBe($secondDatabaseData['users'][0]['email'])
            ->and($firstDatabaseData['users'][0]['credential_marker'])->not->toBe($secondDatabaseData['users'][0]['credential_marker'])
            ->and($firstDatabaseData['orders'][0]['id'])->toBe($secondDatabaseData['orders'][0]['id'])
            ->and($firstDatabaseData['orders'][0]['order_number'])->not->toBe($secondDatabaseData['orders'][0]['order_number'])
            ->and($firstDatabaseData['orders'][0]['description'])->not->toBe($secondDatabaseData['orders'][0]['description']);
    } finally {
        $databases->cleanup();
    }
});

test('refuses a matching database name not created by this helper', function (): void {
    $databases = new DisposablePostgresDatabases();

    expect(fn() => $databases->withDatabase(
        'lumasachi_tenant_test_' . str_repeat('a', 32),
        static fn(PDO $connection): null => null,
    ))->toThrow(RuntimeException::class, 'database not created by this helper');
});

test('refuses database lifecycle on a PostgreSQL host outside the explicit test allowlist', function (): void {
    $originalAllowedHost = config('database.tenant_test_database_allowed_host');
    config(['database.tenant_test_database_allowed_host' => 'not-the-test-host']);

    try {
        expect(fn() => (new DisposablePostgresDatabases())->createPair())
            ->toThrow(RuntimeException::class, 'explicit test allowlist');
    } finally {
        config(['database.tenant_test_database_allowed_host' => $originalAllowedHost]);
    }
});

test('tenant provisioning tracking never claims resources that existed before the attempt', function (): void {
    $databases = new DisposablePostgresDatabases;
    $tenant = Tenant::factory()->make();
    $tenant->database()->makeCredentials();

    $databaseName = $tenant->database()->getName();
    $username = $tenant->database()->getUsername();
    $ownerRoleName = (string)config('tenancy.database.owner_role_prefix')
        . str_replace('-', '', (string)$tenant->getTenantKey());
    $adminConnection = DB::connection();
    $quoteIdentifier = static fn(string $identifier): string => '"' . str_replace('"', '""', $identifier) . '"';

    if (!is_string($databaseName) || !is_string($username)) {
        throw new RuntimeException('The test tenant must have generated database credentials.');
    }

    $resourcesAlreadyExist = $adminConnection->table('pg_database')->where('datname', $databaseName)->exists()
        || $adminConnection->table('pg_roles')->whereIn('rolname', [$username, $ownerRoleName])->exists();

    if ($resourcesAlreadyExist) {
        throw new RuntimeException('The collision-safety test generated an identifier that already exists.');
    }

    $databaseCreated = false;
    $usernameCreated = false;
    $ownerRoleCreated = false;

    try {
        $adminConnection->statement(
            'CREATE ROLE ' . $quoteIdentifier($ownerRoleName)
            . ' NOLOGIN NOSUPERUSER CREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION'
        );
        $ownerRoleCreated = true;
        $adminConnection->statement(
            'CREATE ROLE ' . $quoteIdentifier($username)
            . ' LOGIN PASSWORD ' . $adminConnection->getPdo()->quote('test-only-tenant-role-password')
            . ' NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION'
        );
        $usernameCreated = true;
        $adminConnection->statement(
            'CREATE DATABASE ' . $quoteIdentifier($databaseName) . ' OWNER ' . $quoteIdentifier($ownerRoleName)
        );
        $databaseCreated = true;

        $databases->registerTenantProvisioningAttempt($tenant);

        expect(fn() => $databases->registerProvisionedTenant($tenant))
            ->toThrow(RuntimeException::class, 'not owned by this provisioning attempt');

        $databases->cleanup();

        expect($adminConnection->table('pg_database')->where('datname', $databaseName)->exists())->toBeTrue()
            ->and($adminConnection->table('pg_roles')->where('rolname', $username)->exists())->toBeTrue()
            ->and($adminConnection->table('pg_roles')->where('rolname', $ownerRoleName)->exists())->toBeTrue();
    } finally {
        $databases->cleanup();

        if ($databaseCreated) {
            $adminConnection->statement('DROP DATABASE IF EXISTS ' . $quoteIdentifier($databaseName));
        }

        if ($usernameCreated) {
            $adminConnection->statement('DROP ROLE IF EXISTS ' . $quoteIdentifier($username));
        }

        if ($ownerRoleCreated) {
            $adminConnection->statement('DROP ROLE IF EXISTS ' . $quoteIdentifier($ownerRoleName));
        }
    }
});
