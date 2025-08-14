<?php

namespace Tests\Unit\app\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Company;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

final class CompanyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Company model uses required traits.
     */
    #[Test]
    public function it_checks_if_company_uses_required_traits(): void
    {
        $company = new Company();

        // Check for HasFactory trait
        $this->assertTrue(method_exists($company, 'factory'));
    }

    /**
     * Test that fillable attributes are set correctly.
     */
    #[Test]
    public function it_checks_if_fillable_attributes_are_set_correctly(): void
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
    #[Test]
    public function it_checks_if_casts_attributes_are_set_correctly(): void
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
    #[Test]
    public function it_checks_if_model_table_name_is_correct(): void
    {
        $company = new Company();

        $this->assertEquals('companies', $company->getTable());
    }

    /**
     * Test users relationship.
     */
    #[Test]
    public function it_checks_if_users_relationship_is_correct(): void
    {
        $company = Company::factory()->create();

        $users = User::factory()->count(3)->create(['company_id' => $company->uuid]);

        $this->assertCount(3, $company->users);
        $this->assertEquals(
            $users->pluck('id')->sort()->values()->toArray(),
            $company->users->pluck('id')->sort()->values()->toArray()
        );
    }

    /**
     * Test activeUsers relationship.
     */
    #[Test]
    public function it_checks_if_active_users_relationship_is_correct(): void
    {
        $company = Company::factory()->create();

        User::factory()->count(2)->create(['company_id' => $company->uuid, 'is_active' => true]);
        User::factory()->count(1)->create(['company_id' => $company->uuid, 'is_active' => false]);

        $this->assertCount(2, $company->activeUsers);
    }

    /**
     * Test active scope.
     */
    #[Test]
    public function it_checks_if_active_scope_is_correct(): void
    {
        Company::factory()->count(2)->active()->create();
        Company::factory()->count(1)->inactive()->create();

        $this->assertCount(2, Company::active()->get());
    }

    /**
     * Test inactive scope.
     */
    #[Test]
    public function it_checks_if_inactive_scope_is_correct(): void
    {
        Company::factory()->count(2)->active()->create();
        Company::factory()->count(1)->inactive()->create();

        $this->assertCount(1, Company::inactive()->get());
    }

    /**
     * Test full address accessor.
     */
    #[Test]
    public function it_checks_if_full_address_accessor_is_correct(): void
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
