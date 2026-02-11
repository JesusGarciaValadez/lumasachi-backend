<?php

declare(strict_types=1);

namespace Tests\Feature\app\Models;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserCompanyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete user-company workflow
     */
    #[Test]
    public function it_checks_if_complete_user_company_workflow(): void
    {
        // Create a company
        $company = Company::factory()->create([
            'name' => 'Acme Corporation',
            'email' => 'info@acme.com',
            'is_active' => true,
        ]);

        // Create multiple users with different roles for the company
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::ADMINISTRATOR,
            'first_name' => 'John',
            'last_name' => 'Admin',
        ]);

        $employee = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::EMPLOYEE,
            'first_name' => 'Jane',
            'last_name' => 'Employee',
        ]);

        $customer = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::CUSTOMER,
            'first_name' => 'Bob',
            'last_name' => 'Customer',
        ]);

        // Test that all users are associated with the company
        $this->assertEquals($company->id, $admin->company_id);
        $this->assertEquals($company->id, $employee->company_id);
        $this->assertEquals($company->id, $customer->company_id);

        // Test accessing company from users
        $this->assertEquals('Acme Corporation', $admin->company->name);
        $this->assertEquals('info@acme.com', $employee->company->email);
        $this->assertTrue($customer->company->is_active);

        // Test accessing users from company
        $companyUsers = $company->users()->orderBy('first_name')->get();
        $this->assertCount(3, $companyUsers);
        $this->assertEquals('Bob', $companyUsers[0]->first_name);
        $this->assertEquals('Jane', $companyUsers[1]->first_name);
        $this->assertEquals('John', $companyUsers[2]->first_name);

        // Test filtering users by role through company
        $companyAdmins = $company->users()->where('role', UserRole::ADMINISTRATOR)->get();
        $companyEmployees = $company->users()->where('role', UserRole::EMPLOYEE)->get();
        $companyCustomers = $company->users()->where('role', UserRole::CUSTOMER)->get();

        $this->assertCount(1, $companyAdmins);
        $this->assertCount(1, $companyEmployees);
        $this->assertCount(1, $companyCustomers);

        $this->assertEquals('John', $companyAdmins->first()->first_name);
        $this->assertEquals('Jane', $companyEmployees->first()->first_name);
        $this->assertEquals('Bob', $companyCustomers->first()->first_name);
    }

    /**
     * Test active users relationship
     */
    #[Test]
    public function it_checks_if_company_active_users_relationship(): void
    {
        $company = Company::factory()->create();

        // Create active users
        $activeUsers = User::factory()->count(3)->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        // Create inactive users
        $inactiveUsers = User::factory()->count(2)->create([
            'company_id' => $company->id,
            'is_active' => false,
        ]);

        // Test total users
        $this->assertCount(5, $company->users);

        // Test active users only
        $this->assertCount(3, $company->activeUsers);

        // Verify the active users are the correct ones
        foreach ($activeUsers as $user) {
            $this->assertTrue($company->activeUsers->contains($user));
        }

        // Verify inactive users are not included
        foreach ($inactiveUsers as $user) {
            $this->assertFalse($company->activeUsers->contains($user));
        }
    }

    /**
     * Test querying companies through users
     */
    #[Test]
    public function it_checks_if_querying_companies_through_users(): void
    {
        // Create multiple companies
        $techCompany = Company::factory()->create([
            'name' => 'Tech Corp',
            'city' => 'San Francisco',
        ]);

        $retailCompany = Company::factory()->create([
            'name' => 'Retail Inc',
            'city' => 'New York',
        ]);

        // Create users for each company
        User::factory()->count(2)->create([
            'company_id' => $techCompany->id,
        ]);

        User::factory()->count(3)->create([
            'company_id' => $retailCompany->id,
        ]);

        // Query users with companies in specific cities
        $sfUsers = User::whereHas('company', function ($query) {
            $query->where('city', 'San Francisco');
        })->with('company')->get();

        $nyUsers = User::whereHas('company', function ($query) {
            $query->where('city', 'New York');
        })->with('company')->get();

        $this->assertCount(2, $sfUsers);
        $this->assertCount(3, $nyUsers);

        // Verify all SF users belong to Tech Corp
        foreach ($sfUsers as $user) {
            $this->assertEquals('Tech Corp', $user->company->name);
        }

        // Verify all NY users belong to Retail Inc
        foreach ($nyUsers as $user) {
            $this->assertEquals('Retail Inc', $user->company->name);
        }
    }

    /**
     * Test complex queries with company relationship
     */
    #[Test]
    public function it_checks_if_complex_queries_with_company_relationship(): void
    {
        // Create companies with different statuses
        $activeCompany = Company::factory()->create([
            'name' => 'Active Company',
            'is_active' => true,
        ]);

        $inactiveCompany = Company::factory()->create([
            'name' => 'Inactive Company',
            'is_active' => false,
        ]);

        // Create administrators for active company
        User::factory()->count(2)->create([
            'company_id' => $activeCompany->id,
            'role' => UserRole::ADMINISTRATOR,
            'is_active' => true,
        ]);

        // Create employees for active company
        User::factory()->count(3)->create([
            'company_id' => $activeCompany->id,
            'role' => UserRole::EMPLOYEE,
            'is_active' => true,
        ]);

        // Create users for inactive company
        User::factory()->count(2)->create([
            'company_id' => $inactiveCompany->id,
            'is_active' => true,
        ]);

        // Query active administrators from active companies
        $activeAdminsFromActiveCompanies = User::where('is_active', true)
            ->where('role', UserRole::ADMINISTRATOR)
            ->whereHas('company', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        $this->assertCount(2, $activeAdminsFromActiveCompanies);

        // Query all users from inactive companies
        $usersFromInactiveCompanies = User::whereHas('company', function ($query) {
            $query->where('is_active', false);
        })->get();

        $this->assertCount(2, $usersFromInactiveCompanies);

        // Count users per company using groupBy
        $usersPerCompany = User::selectRaw('company_id, COUNT(*) as user_count')
            ->groupBy('company_id')
            ->whereNotNull('company_id')
            ->get();

        $this->assertCount(2, $usersPerCompany);

        $activeCompanyUserCount = $usersPerCompany->where('company_id', $activeCompany->id)->first();
        $inactiveCompanyUserCount = $usersPerCompany->where('company_id', $inactiveCompany->id)->first();

        $this->assertEquals(5, $activeCompanyUserCount->user_count);
        $this->assertEquals(2, $inactiveCompanyUserCount->user_count);
    }

    /**
     * Test JSON responses include company data
     */
    #[Test]
    public function it_checks_if_json_response_includes_company_data(): void
    {
        $company = Company::factory()->create([
            'name' => 'Test Company',
            'email' => 'test@example.com',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        // Load company relationship
        $user->load('company');

        // Convert to array (as would happen in API response)
        $userData = $user->toArray();

        // Assert company data is included
        $this->assertArrayHasKey('company', $userData);
        $this->assertEquals($company->id, $userData['company']['id']);
        $this->assertEquals($company->uuid, $userData['company']['uuid']);
        $this->assertEquals('Test Company', $userData['company']['name']);
        $this->assertEquals('test@example.com', $userData['company']['email']);
    }
}
