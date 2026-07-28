<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if complete user company workflow', function () {
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
    expect($admin->company_id)->toEqual($company->id);
    expect($employee->company_id)->toEqual($company->id);
    expect($customer->company_id)->toEqual($company->id);

    // Test accessing company from users
    expect($admin->company->name)->toEqual('Acme Corporation');
    expect($employee->company->email)->toEqual('info@acme.com');
    expect($customer->company->is_active)->toBeTrue();

    // Test accessing users from company
    $companyUsers = $company->users()->orderBy('first_name')->get();
    expect($companyUsers)->toHaveCount(3);
    expect($companyUsers[0]->first_name)->toEqual('Bob');
    expect($companyUsers[1]->first_name)->toEqual('Jane');
    expect($companyUsers[2]->first_name)->toEqual('John');

    // Test filtering users by role through company
    $companyAdmins = $company->users()->where('role', UserRole::ADMINISTRATOR)->get();
    $companyEmployees = $company->users()->where('role', UserRole::EMPLOYEE)->get();
    $companyCustomers = $company->users()->where('role', UserRole::CUSTOMER)->get();

    expect($companyAdmins)->toHaveCount(1);
    expect($companyEmployees)->toHaveCount(1);
    expect($companyCustomers)->toHaveCount(1);

    expect($companyAdmins->first()->first_name)->toEqual('John');
    expect($companyEmployees->first()->first_name)->toEqual('Jane');
    expect($companyCustomers->first()->first_name)->toEqual('Bob');
});
it('checks if company active users relationship', function () {
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
    expect($company->users)->toHaveCount(5);

    // Test active users only
    expect($company->activeUsers)->toHaveCount(3);

    // Verify the active users are the correct ones
    foreach ($activeUsers as $user) {
        expect($company->activeUsers->contains($user))->toBeTrue();
    }

    // Verify inactive users are not included
    foreach ($inactiveUsers as $user) {
        expect($company->activeUsers->contains($user))->toBeFalse();
    }
});
it('checks if querying companies through users', function () {
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

    expect($sfUsers)->toHaveCount(2);
    expect($nyUsers)->toHaveCount(3);

    // Verify all SF users belong to Tech Corp
    foreach ($sfUsers as $user) {
        expect($user->company->name)->toEqual('Tech Corp');
    }

    // Verify all NY users belong to Retail Inc
    foreach ($nyUsers as $user) {
        expect($user->company->name)->toEqual('Retail Inc');
    }
});
it('checks if complex queries with company relationship', function () {
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

    expect($activeAdminsFromActiveCompanies)->toHaveCount(2);

    // Query all users from inactive companies
    $usersFromInactiveCompanies = User::whereHas('company', function ($query) {
        $query->where('is_active', false);
    })->get();

    expect($usersFromInactiveCompanies)->toHaveCount(2);

    // Count users per company using groupBy
    $usersPerCompany = User::selectRaw('company_id, COUNT(*) as user_count')
        ->groupBy('company_id')
        ->whereNotNull('company_id')
        ->get();

    expect($usersPerCompany)->toHaveCount(2);

    $activeCompanyUserCount = $usersPerCompany->where('company_id', $activeCompany->id)->first();
    $inactiveCompanyUserCount = $usersPerCompany->where('company_id', $inactiveCompany->id)->first();

    expect($activeCompanyUserCount->user_count)->toEqual(5);
    expect($inactiveCompanyUserCount->user_count)->toEqual(2);
});
it('checks if json response includes company data', function () {
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
    expect($userData)->toHaveKey('company');
    expect($userData['company']['id'])->toEqual($company->id);
    expect($userData['company']['uuid'])->toEqual($company->uuid);
    expect($userData['company']['name'])->toEqual('Test Company');
    expect($userData['company']['email'])->toEqual('test@example.com');
});
