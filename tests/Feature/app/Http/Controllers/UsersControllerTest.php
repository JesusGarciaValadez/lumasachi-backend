<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Enums\UserRole;
use PHPUnit\Framework\Attributes\Test;

final class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authUserWithCompany(?int $companyId = null): User
    {
        $user = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $companyId,
        ]);
        $this->actingAs($user);
        return $user;
    }

    #[Test]
    public function it_returns_employees_of_same_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $me = $this->authUserWithCompany($companyA->id);

        // Same company users
        $sameCompanyUsers = User::factory()->count(3)->create([
            'company_id' => $companyA->id,
        ]);

        // Different company and null company users
        $otherCompanyUsers = User::factory()->count(2)->create(['company_id' => $companyB->id]);
        $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null]);

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
        $otherCompanyUsers = User::factory()->count(3)->create(['company_id' => $companyB->id]);
        $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null]);

        // Same company users
        $sameCompanyUsers = User::factory()->count(2)->create(['company_id' => $companyA->id]);

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

        $nullCompanyUsers = User::factory()->count(3)->create(['company_id' => null]);
        $someCompany = Company::factory()->create();
        $nonNullCompanyUsers = User::factory()->count(2)->create(['company_id' => $someCompany->id]);

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

        $nullCompanyUsers = User::factory()->count(2)->create(['company_id' => null]);
        $someCompany = Company::factory()->create();
        $nonNullCompanyUsers = User::factory()->count(3)->create(['company_id' => $someCompany->id]);

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
}

