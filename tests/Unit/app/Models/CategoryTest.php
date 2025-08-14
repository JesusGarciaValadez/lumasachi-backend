<?php

namespace Tests\Unit\app\Models;

use Tests\TestCase;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_if_can_create_a_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_checks_if_belongs_to_creator(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $category->creator());
        $this->assertEquals($user->id, $category->creator->id);
    }

    #[Test]
    public function it_checks_if_has_many_orders(): void
    {
        $category = Category::factory()->create();
        Order::factory()->createQuietly(['category_id' => $category->id]);

        $this->assertInstanceOf(HasMany::class, $category->orders());
        $this->assertCount(1, $category->orders);
    }

    #[Test]
    public function it_checks_if_can_scope_active_categories(): void
    {
        Category::factory()->count(2)->create(['is_active' => true]);
        Category::factory()->count(3)->create(['is_active' => false]);

        $activeCategories = Category::active()->get();

        $this->assertCount(2, $activeCategories);
    }

    #[Test]
    public function it_checks_if_can_scope_ordered_categories(): void
    {
        $category1 = Category::factory()->create(['name' => 'Category A', 'sort_order' => 2]);
        $category2 = Category::factory()->create(['name' => 'Category B', 'sort_order' => 1]);

        $orderedCategories = Category::ordered()->get();

        $this->assertEquals($category2->id, $orderedCategories->first()->id);
        $this->assertEquals($category1->id, $orderedCategories->last()->id);
    }
}
