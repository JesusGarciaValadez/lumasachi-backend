<?php

namespace Tests\Feature\app\Models;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test company creation.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_create_company(): void
    {
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

        $this->assertInstanceOf(Company::class, $company);
        $this->assertEquals('Test Company', $company->name);
        $this->assertEquals('test@example.com', $company->email);
        $this->assertTrue($company->is_active);
        $this->assertDatabaseHas('companies', [
            'name' => 'Test Company',
            'email' => 'test@example.com'
        ]);
    }

    /**
     * Test company retrieval.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_read_company(): void
    {
        $company = Company::factory()->create();

        // Test finding by UUID
        $foundCompany = Company::find($company->uuid);

        $this->assertInstanceOf(Company::class, $foundCompany);
        $this->assertEquals($company->uuid, $foundCompany->uuid);
        $this->assertEquals($company->name, $foundCompany->name);
        $this->assertEquals($company->email, $foundCompany->email);
    }

    /**
     * Test company update.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_update_company(): void
    {
        $company = Company::factory()->create([
            'name' => 'Original Name'
        ]);

        // Update the company
        $company->update([
            'name' => 'Updated Name',
            'description' => 'Updated description'
        ]);

        // Refresh the model to get the latest data
        $company->refresh();

        $this->assertEquals('Updated Name', $company->name);
        $this->assertEquals('Updated description', $company->description);
        $this->assertDatabaseHas('companies', [
            'uuid' => $company->uuid,
            'name' => 'Updated Name'
        ]);
    }

    /**
     * Test company deletion.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_delete_company(): void
    {
        $company = Company::factory()->create();
        $companyUuid = $company->uuid;

        // Delete the company
        $company->delete();

        // Verify it's deleted from database
        $this->assertDatabaseMissing('companies', ['uuid' => $companyUuid]);

        // Try to find the deleted company
        $deletedCompany = Company::find($companyUuid);
        $this->assertNull($deletedCompany);
    }
}

