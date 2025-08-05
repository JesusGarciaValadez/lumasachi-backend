<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use Modules\Lumasachi\app\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that user belongs to company relationship is properly defined
     */
    #[Test]
    public function user_belongs_to_company_relationship()
    {
        $user = new User();

        $this->assertInstanceOf(BelongsTo::class, $user->company());
        $this->assertEquals('company_id', $user->company()->getForeignKeyName());
        $this->assertEquals('uuid', $user->company()->getOwnerKeyName());
    }

    /**
     * Test that user can be created without a company
     */
    #[Test]
    public function user_can_be_created_without_company()
    {
        $user = User::factory()->create([
            'company_id' => null
        ]);

        $this->assertNull($user->company_id);
        $this->assertNull($user->company);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'company_id' => null
        ]);
    }

    /**
     * Test that user can be associated with a company
     */
    #[Test]
    public function user_can_be_associated_with_company()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->uuid
        ]);

        $this->assertEquals($company->uuid, $user->company_id);
        $this->assertInstanceOf(Company::class, $user->company);
        $this->assertEquals($company->uuid, $user->company->uuid);
        $this->assertEquals($company->name, $user->company->name);
    }

    /**
     * Test that user can access company attributes through relationship
     */
    #[Test]
    public function user_can_access_company_attributes()
    {
        $companyData = [
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'phone' => '123-456-7890',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country' => 'Test Country',
            'website' => 'https://testcompany.com'
        ];

        $company = Company::factory()->create($companyData);
        $user = User::factory()->create([
            'company_id' => $company->uuid
        ]);

        $this->assertEquals($companyData['name'], $user->company->name);
        $this->assertEquals($companyData['email'], $user->company->email);
        $this->assertEquals($companyData['phone'], $user->company->phone);
        $this->assertEquals($companyData['address'], $user->company->address);
        $this->assertEquals($companyData['city'], $user->company->city);
        $this->assertEquals($companyData['state'], $user->company->state);
        $this->assertEquals($companyData['postal_code'], $user->company->postal_code);
        $this->assertEquals($companyData['country'], $user->company->country);
        $this->assertEquals($companyData['website'], $user->company->website);
    }

    /**
     * Test that multiple users can belong to the same company
     */
    #[Test]
    public function multiple_users_can_belong_to_same_company()
    {
        $company = Company::factory()->create();
        $users = User::factory()->count(3)->create([
            'company_id' => $company->uuid
        ]);

        foreach ($users as $user) {
            $this->assertEquals($company->uuid, $user->company_id);
            $this->assertEquals($company->uuid, $user->company->uuid);
        }

        // Test from company perspective
        $this->assertCount(3, $company->users);
        $this->assertTrue($company->users->contains($users[0]));
        $this->assertTrue($company->users->contains($users[1]));
        $this->assertTrue($company->users->contains($users[2]));
    }

    /**
     * Test that changing user's company updates the relationship
     */
    #[Test]
    public function changing_users_company_updates_relationship()
    {
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $company2 = Company::factory()->create(['name' => 'Company 2']);

        $user = User::factory()->create([
            'company_id' => $company1->uuid
        ]);

        // Initial state
        $this->assertEquals($company1->uuid, $user->company_id);
        $this->assertEquals('Company 1', $user->company->name);

        // Update company
        $user->update(['company_id' => $company2->uuid]);
        $user->refresh();

        // New state
        $this->assertEquals($company2->uuid, $user->company_id);
        $this->assertEquals('Company 2', $user->company->name);
    }

    /**
     * Test that user's company can be removed
     */
    #[Test]
    public function users_company_can_be_removed()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->uuid
        ]);

        // Initial state
        $this->assertNotNull($user->company);

        // Remove company
        $user->update(['company_id' => null]);
        $user->refresh();

        // New state
        $this->assertNull($user->company_id);
        $this->assertNull($user->company);
    }

    /**
     * Test eager loading of company relationship
     */
    #[Test]
    public function eager_loading_company_relationship()
    {
        $company = Company::factory()->create();
        User::factory()->count(3)->create([
            'company_id' => $company->uuid
        ]);

        // Test that eager loading prevents N+1 queries
        $users = User::with('company')->get();

        foreach ($users as $user) {
            // This should not trigger additional queries
            $this->assertInstanceOf(Company::class, $user->company);
            $this->assertEquals($company->name, $user->company->name);
        }
    }

    /**
     * Test that company_id is fillable
     */
    #[Test]
    public function company_id_is_fillable()
    {
        $user = new User();
        $fillable = $user->getFillable();

        $this->assertContains('company_id', $fillable);
    }

    /**
     * Test querying users by company
     */
    #[Test]
    public function querying_users_by_company()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        // Create users for company 1
        User::factory()->count(2)->create([
            'company_id' => $company1->uuid
        ]);

        // Create users for company 2
        User::factory()->count(3)->create([
            'company_id' => $company2->uuid
        ]);

        // Create users without company
        User::factory()->count(1)->create([
            'company_id' => null
        ]);

        // Query users by company
        $company1Users = User::where('company_id', $company1->uuid)->get();
        $company2Users = User::where('company_id', $company2->uuid)->get();
        $usersWithoutCompany = User::whereNull('company_id')->get();

        $this->assertCount(2, $company1Users);
        $this->assertCount(3, $company2Users);
        $this->assertCount(1, $usersWithoutCompany);
    }

    /**
     * Test that deleting a company sets user's company_id to null (due to nullOnDelete)
     */
    #[Test]
    public function deleting_company_sets_users_company_id_to_null()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->uuid
        ]);

        // Initial state
        $this->assertEquals($company->uuid, $user->company_id);

        // Delete company
        $company->delete();
        $user->refresh();

        // User should still exist but company_id should be null
        $this->assertNull($user->company_id);
        $this->assertNull($user->company);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'company_id' => null
        ]);
    }
}
