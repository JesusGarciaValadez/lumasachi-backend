<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_employees_of_same_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $me = $this->authUserWithCompany($companyA->id);

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
        $this->assertTrue($ids->contains($me->id));
        foreach ($sameCompanyUsers as $u) {
            $this->assertTrue($ids->contains($u->id));
        }
        // Should not contain different or null company users
        foreach ($otherCompanyUsers as $u) {
            $this->assertFalse($ids->contains($u->id));
        }
        foreach ($nullCompanyUsers as $u) {
            $this->assertFalse($ids->contains($u->id));
        }
    }

    #[Test]
    public function it_returns_customers_of_different_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $me = $this->authUserWithCompany($companyA->id);

        // Different company users (including null)
        $otherCompanyUsers = User::factory()->count(3)->create(['company_id' => $companyB->id, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);
        $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);

        // Same company users
        $sameCompanyUsers = User::factory()->count(2)->create(['company_id' => $companyA->id, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);

        $response = $this->getJson('/api/v1/users/customers');
        $response->assertOk();

        $ids = collect($response->json())->pluck('id');

        foreach ($otherCompanyUsers as $u) {
            $this->assertTrue($ids->contains($u->id));
        }
        foreach ($nullCompanyUsers as $u) {
            $this->assertTrue($ids->contains($u->id));
        }
        foreach ($sameCompanyUsers as $u) {
            $this->assertFalse($ids->contains($u->id));
        }
        // Current user should not be listed (their company is same as me)
        $this->assertFalse($ids->contains($me->id));
    }

    #[Test]
    public function it_handles_null_company_id_for_employees(): void
    {
        $me = $this->authUserWithCompany(null);

        $nullCompanyUsers = User::factory()->count(3)->create(['company_id' => null, 'role' => UserRole::EMPLOYEE->value, 'is_active' => true]);
        $someCompany = Company::factory()->create();
        $nonNullCompanyUsers = User::factory()->count(2)->create(['company_id' => $someCompany->id, 'role' => UserRole::EMPLOYEE->value, 'is_active' => true]);

        $response = $this->getJson('/api/v1/users/employees');
        $response->assertOk();

        $ids = collect($response->json())->pluck('id');
        // Should include me and null-company users
        $this->assertTrue($ids->contains($me->id));
        foreach ($nullCompanyUsers as $u) {
            $this->assertTrue($ids->contains($u->id));
        }
        // Should not include non-null company users
        foreach ($nonNullCompanyUsers as $u) {
            $this->assertFalse($ids->contains($u->id));
        }
    }

    #[Test]
    public function it_handles_null_company_id_for_customers(): void
    {
        $this->authUserWithCompany(null);

        $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);
        $someCompany = Company::factory()->create();
        $nonNullCompanyUsers = User::factory()->count(3)->create(['company_id' => $someCompany->id, 'role' => UserRole::CUSTOMER->value, 'is_active' => true]);

        $response = $this->getJson('/api/v1/users/customers');
        $response->assertOk();

        $ids = collect($response->json())->pluck('id');
        // Should include only non-null company users
        foreach ($nonNullCompanyUsers as $u) {
            $this->assertTrue($ids->contains($u->id));
        }
        foreach ($nullCompanyUsers as $u) {
            $this->assertFalse($ids->contains($u->id));
        }
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/v1/users/employees')->assertUnauthorized();
        $this->getJson('/api/v1/users/customers')->assertUnauthorized();
    }

    private function authUserWithCompany(?int $companyId = null): User
    {
        $user = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $companyId,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        return $user;
    }
}
