<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('returns employees of same company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $me = authUserWithCompany($companyA->id);

    // Same company users
    $sameCompanyUsers = User::factory()->count(3)->create([
        'company_id' => $companyA->id,
        'role' => UserRole::EMPLOYEE->value,
        'is_active' => true,
    ]);

    // Different company and null company users
    $otherCompanyUsers = User::factory()->count(2)->create(['company_id' => $companyB->id, 'role' => UserRole::EMPLOYEE->value, 'is_active' => true]);
    $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null, 'role' => UserRole::EMPLOYEE->value, 'is_active' => true]);

    $response = $this->getJson('/api/v1/users/employees');
    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    // Should contain me and same company users
    expect($ids->contains($me->id))->toBeTrue();
    foreach ($sameCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeTrue();
    }

    // Should not contain different or null company users
    foreach ($otherCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeFalse();
    }
    foreach ($nullCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeFalse();
    }
});
it('returns customers of different company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $me = authUserWithCompany($companyA->id);

    // Different company users (including null)
    $otherCompanyUsers = User::factory()->count(3)->create(['company_id' => $companyB->id, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);
    $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);

    // Same company users
    $sameCompanyUsers = User::factory()->count(2)->create(['company_id' => $companyA->id, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);

    $response = $this->getJson('/api/v1/users/customers');
    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    foreach ($otherCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeTrue();
    }
    foreach ($nullCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeTrue();
    }
    foreach ($sameCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeFalse();
    }

    // Current user should not be listed (their company is same as me)
    expect($ids->contains($me->id))->toBeFalse();
});
it('handles null company id for employees', function () {
    $me = authUserWithCompany(null);

    $nullCompanyUsers = User::factory()->count(3)->create(['company_id' => null, 'role' => UserRole::EMPLOYEE->value, 'is_active' => true]);
    $someCompany = Company::factory()->create();
    $nonNullCompanyUsers = User::factory()->count(2)->create(['company_id' => $someCompany->id, 'role' => UserRole::EMPLOYEE->value, 'is_active' => true]);

    $response = $this->getJson('/api/v1/users/employees');
    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    // Should include me and null-company users
    expect($ids->contains($me->id))->toBeTrue();
    foreach ($nullCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeTrue();
    }

    // Should not include non-null company users
    foreach ($nonNullCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeFalse();
    }
});
it('handles null company id for customers', function () {
    authUserWithCompany(null);

    $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);
    $someCompany = Company::factory()->create();
    $nonNullCompanyUsers = User::factory()->count(3)->create(['company_id' => $someCompany->id, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);

    $response = $this->getJson('/api/v1/users/customers');
    $response->assertOk();

    $ids = collect($response->json())->pluck('id');

    // Should include only non-null company users
    foreach ($nonNullCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeTrue();
    }
    foreach ($nullCompanyUsers as $u) {
        expect($ids->contains($u->id))->toBeFalse();
    }
});
it('requires authentication', function () {
    $this->getJson('/api/v1/users/employees')->assertUnauthorized();
    $this->getJson('/api/v1/users/customers')->assertUnauthorized();
});
function authUserWithCompany(?int $companyId = null): User
{
    $user = User::factory()->create([
        'role' => UserRole::EMPLOYEE->value,
        'company_id' => $companyId,
        'is_active' => true,
    ]);
    test()->actingAs($user);

    return $user;
}
