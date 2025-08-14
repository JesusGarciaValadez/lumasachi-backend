<?php

namespace Tests\Feature\app\Models;

use Tests\TestCase;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class CategoryOrderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_if_category_workflow_with_orders(): void
    {
        // Create users
        $admin = User::factory()->create();
        $customer = User::factory()->create();

        // Create active categories
        $maintenanceCategory = Category::create([
            'name' => 'Mantenimiento',
            'description' => 'Trabajos de mantenimiento',
            'is_active' => true,
            'sort_order' => 1,
            'color' => '#3B82F6',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $developmentCategory = Category::create([
            'name' => 'Desarrollo',
            'description' => 'Desarrollo de software',
            'is_active' => true,
            'sort_order' => 2,
            'color' => '#EC4899',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        // Create inactive category
        $oldCategory = Category::create([
            'name' => 'Categoría Antigua',
            'description' => 'Ya no se usa',
            'is_active' => false,
            'sort_order' => 99,
            'color' => '#6B7280',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        // Create orders for maintenance category
        $maintenanceOrders = Order::factory()->count(3)->createQuietly([
            'category_id' => $maintenanceCategory->id,
            'customer_id' => $customer->id,
        ]);

        // Create orders for development category
        $developmentOrders = Order::factory()->count(2)->createQuietly([
            'category_id' => $developmentCategory->id,
            'customer_id' => $customer->id,
        ]);

        // Test category relationships
        $this->assertCount(3, $maintenanceCategory->orders);
        $this->assertCount(2, $developmentCategory->orders);
        $this->assertCount(0, $oldCategory->orders);

        // Test active scope
        $activeCategories = Category::active()->get();
        $this->assertCount(2, $activeCategories);
        $this->assertTrue($activeCategories->contains($maintenanceCategory));
        $this->assertTrue($activeCategories->contains($developmentCategory));
        $this->assertFalse($activeCategories->contains($oldCategory));

        // Test ordered scope
        $orderedCategories = Category::ordered()->get();
        $this->assertEquals($maintenanceCategory->id, $orderedCategories->first()->id);
        $this->assertEquals($developmentCategory->id, $orderedCategories[1]->id);

        // Test order category relationship
        foreach ($maintenanceOrders as $order) {
            $this->assertEquals('Mantenimiento', $order->category->name);
            $this->assertEquals('#3B82F6', $order->category->color);
        }

        foreach ($developmentOrders as $order) {
            $this->assertEquals('Desarrollo', $order->category->name);
            $this->assertEquals('#EC4899', $order->category->color);
        }

        // Test querying orders by category
        $maintenanceOrdersQuery = Order::whereHas('category', function ($query) {
            $query->where('name', 'Mantenimiento');
        })->get();

        $this->assertCount(3, $maintenanceOrdersQuery);

        // Test eager loading
        $ordersWithCategory = Order::with('category')->get();
        foreach ($ordersWithCategory as $order) {
            $this->assertNotNull($order->category);
            $this->assertTrue($order->relationLoaded('category'));
        }
    }

    #[Test]
    public function it_checks_if_orders_can_exist_without_category(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->createQuietly([
            'category_id' => null,
            'customer_id' => $user->id,
        ]);

        $this->assertNull($order->category_id);
        $this->assertNull($order->category);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'category_id' => null,
        ]);
    }

    #[Test]
    public function it_checks_if_deleting_category_sets_order_category_to_null(): void
    {
        $category = Category::factory()->create();
        $order = Order::factory()->createQuietly(['category_id' => $category->id]);

        // Verify initial state
        $this->assertEquals($category->id, $order->category_id);

        // Delete category
        $category->delete();

        // Refresh order and verify category_id is null
        $order->refresh();
        $this->assertNull($order->category_id);
        $this->assertNull($order->category);
    }
}
