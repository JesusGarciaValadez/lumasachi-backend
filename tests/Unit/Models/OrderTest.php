<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_an_order()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $order = Order::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'created_by' => $user->id,
            'category_id' => $category->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_category()
    {
        $category = Category::factory()->create();
        $order = Order::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(BelongsTo::class, $order->category());
        $this->assertEquals($category->id, $order->category->id);
    }

    #[Test]
    public function it_belongs_to_a_creator()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $order->createdBy());
        $this->assertEquals($user->id, $order->createdBy->id);
    }
}

