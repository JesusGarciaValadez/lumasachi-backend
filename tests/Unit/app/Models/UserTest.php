<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user belongs to company relationship', function () {
    $user = new User();

    expect($user->company())->toBeInstanceOf(BelongsTo::class);
    expect($user->company()->getForeignKeyName())->toEqual('company_id');
    expect($user->company()->getOwnerKeyName())->toEqual('id');
});
test('user can be created without company', function () {
    $user = User::factory()->create([
        'company_id' => null,
    ]);

    expect($user->company_id)->toBeNull();
    expect($user->company)->toBeNull();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'company_id' => null,
    ]);
});
test('user can be associated with company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    expect($user->company_id)->toEqual($company->id);
    expect($user->company)->toBeInstanceOf(Company::class);
    expect($user->company->id)->toEqual($company->id);
    expect($user->company->name)->toEqual($company->name);
});
test('user can access company attributes', function () {
    $companyData = [
        'name' => 'Test Company',
        'email' => 'test@company.com',
        'phone' => '123-456-7890',
        'address' => '123 Test St',
        'city' => 'Test City',
        'state' => 'TS',
        'postal_code' => '12345',
        'country' => 'Test Country',
        'website' => 'https://testcompany.com',
    ];

    $company = Company::factory()->create($companyData);
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    expect($user->company->name)->toEqual($companyData['name']);
    expect($user->company->email)->toEqual($companyData['email']);
    expect($user->company->phone)->toEqual($companyData['phone']);
    expect($user->company->address)->toEqual($companyData['address']);
    expect($user->company->city)->toEqual($companyData['city']);
    expect($user->company->state)->toEqual($companyData['state']);
    expect($user->company->postal_code)->toEqual($companyData['postal_code']);
    expect($user->company->country)->toEqual($companyData['country']);
    expect($user->company->website)->toEqual($companyData['website']);
});
test('multiple users can belong to same company', function () {
    $company = Company::factory()->create();
    $users = User::factory()->count(3)->create([
        'company_id' => $company->id,
    ]);

    foreach ($users as $user) {
        expect($user->company_id)->toEqual($company->id);
        expect($user->company->id)->toEqual($company->id);
    }

    // Test from company perspective
    expect($company->users)->toHaveCount(3);
    expect($company->users->contains($users[0]))->toBeTrue();
    expect($company->users->contains($users[1]))->toBeTrue();
    expect($company->users->contains($users[2]))->toBeTrue();
});
test('changing users company updates relationship', function () {
    $company1 = Company::factory()->create(['name' => 'Company 1']);
    $company2 = Company::factory()->create(['name' => 'Company 2']);

    $user = User::factory()->create([
        'company_id' => $company1->id,
    ]);

    // Initial state
    expect($user->company_id)->toEqual($company1->id);
    expect($user->company->name)->toEqual('Company 1');

    // Update company
    $user->update(['company_id' => $company2->id]);
    $user->refresh();

    // New state
    expect($user->company_id)->toEqual($company2->id);
    expect($user->company->name)->toEqual('Company 2');
});
test('users company can be removed', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    // Initial state
    expect($user->company)->not->toBeNull();

    // Remove company
    $user->update(['company_id' => null]);
    $user->refresh();

    // New state
    expect($user->company_id)->toBeNull();
    expect($user->company)->toBeNull();
});
test('eager loading company relationship', function () {
    $company = Company::factory()->create();
    User::factory()->count(3)->create([
        'company_id' => $company->id,
    ]);

    // Test that eager loading prevents N+1 queries
    $users = User::with('company')->get();

    foreach ($users as $user) {
        // This should not trigger additional queries
        expect($user->company)->toBeInstanceOf(Company::class);
        expect($user->company->name)->toEqual($company->name);
    }
});
test('company id is fillable', function () {
    $user = new User();
    $fillable = $user->getFillable();

    expect($fillable)->toContain('company_id');
});
test('querying users by company', function () {
    $company1 = Company::factory()->create();
    $company2 = Company::factory()->create();

    // Create users for company 1
    User::factory()->count(2)->create([
        'company_id' => $company1->id,
    ]);

    // Create users for company 2
    User::factory()->count(3)->create([
        'company_id' => $company2->id,
    ]);

    // Create users without company
    User::factory()->count(1)->create([
        'company_id' => null,
    ]);

    // Query users by company
    $company1Users = User::where('company_id', $company1->id)->get();
    $company2Users = User::where('company_id', $company2->id)->get();
    $usersWithoutCompany = User::whereNull('company_id')->get();

    expect($company1Users)->toHaveCount(2);
    expect($company2Users)->toHaveCount(3);
    expect($usersWithoutCompany)->toHaveCount(1);
});
test('deleting company sets users company id to null', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    // Initial state
    expect($user->company_id)->toEqual($company->id);

    // Delete company
    $company->delete();
    $user->refresh();

    // User should still exist but company_id should be null
    expect($user->company_id)->toBeNull();
    expect($user->company)->toBeNull();
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'company_id' => null,
    ]);
});
