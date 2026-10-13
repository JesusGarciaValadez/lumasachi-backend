<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('tenant database password is encrypted in storage and hidden from serialization', function (): void {
    $databasePassword = 'internal-db-password-tenant-feature-test';
    $tenant = Tenant::factory()->create();
    $tenant->setInternal('db_password', $databasePassword);
    $tenant->save();

    $freshTenant = $tenant->fresh();

    if ($freshTenant === null) {
        throw new RuntimeException('The tenant registry record must still exist.');
    }

    expect($freshTenant->getInternal('db_password'))->toBe($databasePassword);

    $storedTenantData = DB::table($tenant->getTable())
        ->where('id', $tenant->getKey())
        ->value('data');

    if (!is_string($storedTenantData)) {
        throw new RuntimeException('The tenant internal data must be stored as serialized JSON.');
    }

    expect($storedTenantData)->not->toContain($databasePassword)
        ->and($freshTenant->toArray())->not->toHaveKey('tenancy_db_password')
        ->and($freshTenant->toJson())->not->toContain($databasePassword);
});
