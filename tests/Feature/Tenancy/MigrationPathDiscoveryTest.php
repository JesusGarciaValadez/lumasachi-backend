<?php

declare(strict_types=1);

use App\Providers\MigrationPathServiceProvider;
use Illuminate\Database\Migrations\Migrator;

test('default migration discovery uses central migrations outside testing and legacy migrations in testing', function (): void {
    $discoverMigrationNames = static function (string $environment): array {
        $application = app();
        $originalEnvironment = $application->environment();
        $originalMigrator = $application->make('migrator');
        $migrator = new Migrator($originalMigrator->getRepository(), app('db'), app('files'));
        $application->instance('env', $environment);
        $application->instance('migrator', $migrator);

        try {
            (new MigrationPathServiceProvider($application))->boot();

            $defaultPaths = array_merge($migrator->paths(), [database_path('migrations')]);

            return array_keys($migrator->getMigrationFiles($defaultPaths));
        } finally {
            $application->instance('env', $originalEnvironment);
            $application->instance('migrator', $originalMigrator);
        }
    };

    $centralMigrationNames = $discoverMigrationNames('local');
    $testingMigrationNames = $discoverMigrationNames('testing');

    expect($centralMigrationNames)
        ->toContain('2026_10_10_012644_create_central_companies_table');

    expect(in_array('2025_07_27_164818_create_orders_table', $centralMigrationNames, true))->toBeFalse()
        ->and($testingMigrationNames)->toContain('2025_07_27_164818_create_orders_table');

    expect(in_array('2026_10_10_012644_create_central_companies_table', $testingMigrationNames, true))->toBeFalse();

    expect(app('migrator')->paths())
        ->toContain(database_path('migrations/legacy'));
});
