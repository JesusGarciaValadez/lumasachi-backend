<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Modules\Lumasachi\app\Models\Category;
use Modules\Lumasachi\app\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_category()
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
    public function it_belongs_to_creator()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $category->creator());
        $this->assertEquals($user->id, $category->creator->id);
    }

    #[Test]
    public function it_has_many_orders()
    {
        $category = Category::factory()->create();
        Order::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(HasMany::class, $category->orders());
        $this->assertCount(1, $category->orders);
    }

    #[Test]
    public function it_can_scope_active_categories()
    {
        Category::factory()->count(2)->create(['is_active' => true]);
        Category::factory()->count(3)->create(['is_active' => false]);

        $activeCategories = Category::active()->get();

        $this->assertCount(2, $activeCategories);
    }

    #[Test]
    public function it_can_scope_ordered_categories()
    {
        $category1 = Category::factory()->create(['name' => 'Category A', 'sort_order' => 2]);
        $category2 = Category::factory()->create(['name' => 'Category B', 'sort_order' => 1]);

        $orderedCategories = Category::ordered()->get();

        $this->assertEquals($category2->id, $orderedCategories->first()->id);
        $this->assertEquals($category1->id, $orderedCategories->last()->id);
    }
}
