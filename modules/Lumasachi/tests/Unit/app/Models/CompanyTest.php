<?php

namespace Modules\Lumasachi\Tests\Unit\app\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Lumasachi\app\Models\Company;

final class CompanyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Company model uses required traits.
     */
    public function test_company_uses_required_traits(): void
    {
        $company = new Company();

        // Check for HasFactory trait
        $this->assertTrue(method_exists($company, 'factory'));
    }

    /**
     * Test that fillable attributes are set correctly.
     */
    public function test_fillable_attributes(): void
    {
        $company = new Company();
        $fillable = $company->getFillable();

        $expectedFillable = [
            'name',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'description',
            'website',
            'logo',
            'is_active',
            'tax_id',
            'contact_person',
            'contact_email',
            'contact_phone',
            'notes',
            'settings',
        ];

        $this->assertEquals($expectedFillable, $fillable);
    }

    /**
     * Test that casts are set correctly.
     */
    public function test_casts_attributes(): void
    {
        $company = new Company();
        $casts = $company->getCasts();

        $this->assertArrayHasKey('is_active', $casts);
        $this->assertArrayHasKey('settings', $casts);
        $this->assertArrayHasKey('created_at', $casts);
        $this->assertArrayHasKey('updated_at', $casts);

        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('json', $casts['settings']);
        $this->assertStringContainsString('datetime', $casts['created_at']);
        $this->assertStringContainsString('datetime', $casts['updated_at']);
    }

    /**
     * Test model table name.
     */
    public function test_model_table_name(): void
    {
        $company = new Company();

        $this->assertEquals('companies', $company->getTable());
    }

    /**
     * Test users relationship.
     */
    public function test_users_relationship(): void
    {
        $company = Company::factory()->create();

        $users = \App\Models\User::factory()->count(3)->create(['company_id' => $company->uuid]);

        $this->assertCount(3, $company->users);
        $this->assertEquals($users->pluck('id')->toArray(), $company->users->pluck('id')->toArray());
    }

    /**
     * Test activeUsers relationship.
     */
    public function test_active_users_relationship(): void
    {
        $company = Company::factory()->create();

        \App\Models\User::factory()->count(2)->create(['company_id' => $company->uuid, 'is_active' => true]);
        \App\Models\User::factory()->count(1)->create(['company_id' => $company->uuid, 'is_active' => false]);

        $this->assertCount(2, $company->activeUsers);
    }

    /**
     * Test active scope.
     */
    public function test_active_scope(): void
    {
        Company::factory()->count(2)->active()->create();
        Company::factory()->count(1)->inactive()->create();

        $this->assertCount(2, Company::active()->get());
    }

    /**
     * Test inactive scope.
     */
    public function test_inactive_scope(): void
    {
        Company::factory()->count(2)->active()->create();
        Company::factory()->count(1)->inactive()->create();

        $this->assertCount(1, Company::inactive()->get());
    }

    /**
     * Test full address accessor.
     */
    public function test_full_address_accessor(): void
    {
        $company = Company::factory()->create([
            'address' => '123 Main St',
            'city' => 'Sample City',
            'state' => 'State',
            'postal_code' => '12345',
            'country' => 'Country'
        ]);

        $expectedFullAddress = '123 Main St, Sample City, State, 12345, Country';

        $this->assertEquals($expectedFullAddress, $company->fullAddress);
    }
}
