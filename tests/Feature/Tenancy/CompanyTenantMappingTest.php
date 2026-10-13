<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a company registry record maps to its matching tenancy record on the central connection', function (): void {
    $company = Company::factory()->create();
    $tenant = Tenant::factory()->create(['id' => $company->uuid]);

    $freshCompany = $company->fresh();
    $freshTenant = $tenant->fresh();

    if ($freshCompany === null || $freshTenant === null) {
        throw new RuntimeException('The company and tenancy registry records must still exist.');
    }

    $companyTenant = $freshCompany->tenant;
    $tenantCompany = $freshTenant->company;

    if ($companyTenant === null || $tenantCompany === null) {
        throw new RuntimeException('The company and tenancy records must have a reciprocal UUID mapping.');
    }

    expect($freshCompany->getConnectionName())->toBe(config('tenancy.database.central_connection'))
        ->and($freshTenant->getConnectionName())->toBe(config('tenancy.database.central_connection'))
        ->and($companyTenant->is($freshTenant))->toBeTrue()
        ->and($tenantCompany->is($freshCompany))->toBeTrue();
});
