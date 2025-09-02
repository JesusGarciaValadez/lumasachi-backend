<?php

namespace Tests\Feature\app\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Category;
use App\Models\Company;
use PHPUnit\Framework\Attributes\Test;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'uuid' => fake()->uuid(),
            'company_id' => $this->company->id,
            'role' => UserRole::EMPLOYEE,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_returns_a_list_of_categories_for_the_users_company(): void
    {
        Sanctum::actingAs($this->user);

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::EMPLOYEE,
            'is_active' => true,
        ]);

        // Categories for the user's company
        Category::factory()->count(3)->create([
            'is_active' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        // Categories for another company
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => true,
        ]);
        Category::factory()->count(2)->create([
            'is_active' => true,
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    #[Test]
    public function it_returns_an_empty_list_if_no_categories_for_the_users_company(): void
    {
        Sanctum::actingAs($this->user);

        // Categories for another company
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create([
            'company_id' => $otherCompany->id,
            'role' => UserRole::EMPLOYEE,
            'is_active' => true,
        ]);
        Category::factory()->count(5)->create([
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonCount(0);
    }

    #[Test]
    public function it_returns_a_401_unauthorized_error_for_unauthenticated_users(): void
    {
        $response = $this->getJson('/api/v1/categories');
        $response->assertStatus(401);
    }

    #[Test]
    public function it_can_bulk_store_categories_for_authenticated_users(): void
    {
        Sanctum::actingAs($this->user);
        $categoriesData = [
            'categories' => [
                ['name' => 'Category A', 'description' => 'Description A'],
                ['name' => 'Category B', 'is_active' => false],
                ['name' => 'Category C'],
            ]
        ];

        $response = $this->postJson('/api/v1/categories/bulk', $categoriesData);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Categories created successfully.']);

        $this->assertDatabaseCount('categories', 3);
        $this->assertDatabaseHas('categories', ['name' => 'Category A']);
        $this->assertDatabaseHas('categories', ['name' => 'Category B', 'is_active' => false]);
        $this->assertDatabaseHas('categories', ['name' => 'Category C', 'is_active' => true]); // Default
    }

    #[Test]
    public function it_returns_validation_error_for_invalid_data_on_bulk_store(): void
    {
        Sanctum::actingAs($this->user);
        $invalidData = [
            'categories' => [
                ['description' => 'Missing name'], // Name is required
                ['name' => ''], // Name cannot be empty
                ['name' => 'Unique Category'],
                ['name' => 'Unique Category'], // Name must be unique
            ]
        ];

        $response = $this->postJson('/api/v1/categories/bulk', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'categories.0.name',
                'categories.1.name',
                'categories.3.name'
            ]);
    }

    #[Test]
    public function it_returns_a_401_error_for_unauthenticated_users_on_bulk_store(): void
    {
        $response = $this->postJson('/api/v1/categories/bulk', [
            'categories' => [['name' => 'Test']]
        ]);
        $response->assertStatus(401);
    }

    #[Test]
    public function it_can_delete_a_category(): void
    {
        Sanctum::actingAs($this->user);

        $category = Category::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$category->uuid}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Category deleted successfully.']);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    #[Test]
    public function it_returns_a_401_error_for_unauthenticated_users_on_delete(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/categories/{$category->uuid}");

        $response->assertStatus(401);
    }

    #[Test]
    public function it_returns_a_403_error_if_user_is_a_customer_on_delete(): void
    {
        $customerUser = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customerUser);

        $category = Category::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$category->uuid}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_a_403_error_if_deleting_a_category_from_another_company(): void
    {
        Sanctum::actingAs($this->user);

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherCategory = Category::factory()->create([
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/v1/categories/{$otherCategory->uuid}");

        $response->assertStatus(403);
    }
}

