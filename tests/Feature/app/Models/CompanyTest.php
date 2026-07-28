<?php

declare(strict_types=1);

use App\Models\Company;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if create company', function () {
    $companyData = [
        'name' => 'Test Company',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'address' => '123 Test Street',
        'city' => 'Test City',
        'state' => 'Test State',
        'postal_code' => '12345',
        'country' => 'Test Country',
        'website' => 'http://www.test.com',
        'description' => 'A test company',
        'is_active' => true,
        'contact_person' => 'John Doe',
        'contact_email' => 'john@example.com',
        'contact_phone' => '9876543210',
    ];

    $company = Company::create($companyData);

    expect($company)->toBeInstanceOf(Company::class);
    expect($company->name)->toEqual('Test Company');
    expect($company->email)->toEqual('test@example.com');
    expect($company->is_active)->toBeTrue();
    $this->assertDatabaseHas('companies', [
        'name' => 'Test Company',
        'email' => 'test@example.com',
    ]);
});
it('checks if read company', function () {
    $company = Company::factory()->create();

    // Test finding by UUID
    $foundCompany = Company::find($company->id);

    expect($foundCompany)->toBeInstanceOf(Company::class);
    expect($foundCompany->id)->toEqual($company->id);
    expect($foundCompany->uuid)->toEqual($company->uuid);
    expect($foundCompany->name)->toEqual($company->name);
    expect($foundCompany->email)->toEqual($company->email);
});
it('checks if update company', function () {
    $company = Company::factory()->create([
        'name' => 'Original Name',
    ]);

    // Update the company
    $company->update([
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ]);

    // Refresh the model to get the latest data
    $company->refresh();

    expect($company->name)->toEqual('Updated Name');
    expect($company->description)->toEqual('Updated description');
    $this->assertDatabaseHas('companies', [
        'uuid' => $company->uuid,
        'name' => 'Updated Name',
    ]);
});
it('checks if delete company', function () {
    $company = Company::factory()->create();
    $companyId = $company->id;

    // Delete the company
    $company->delete();

    // Verify it's deleted from database
    $this->assertDatabaseMissing('companies', ['id' => $companyId]);

    // Try to find the deleted company
    $deletedCompany = Company::find($companyId);
    expect($deletedCompany)->toBeNull();
});
