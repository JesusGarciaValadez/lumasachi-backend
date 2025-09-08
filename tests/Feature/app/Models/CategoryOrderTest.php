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
            // 'category_id' => $maintenanceCategory->id,
            'customer_id' => $customer->id,
        ])->each(function ($order) use ($maintenanceCategory) {
            $order->categories()->attach($maintenanceCategory->id);
        });

        // Create orders for development category
        $developmentOrders = Order::factory()->count(2)->createQuietly([
            // 'category_id' => $developmentCategory->id,
            'customer_id' => $customer->id,
        ])->each(function ($order) use ($developmentCategory) {
            $order->categories()->attach($developmentCategory->id);
        });

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

        // Test order categories relationship
        foreach ($maintenanceOrders as $order) {
            $this->assertCount(1, $order->categories);
            $this->assertEquals('Mantenimiento', $order->categories->first()->name);
            $this->assertEquals('#3B82F6', $order->categories->first()->color);
        }

        foreach ($developmentOrders as $order) {
            $this->assertCount(1, $order->categories);
            $this->assertEquals('Desarrollo', $order->categories->first()->name);
            $this->assertEquals('#EC4899', $order->categories->first()->color);
        }

        // Test querying orders by category
        $maintenanceOrdersQuery = Order::whereHas('categories', function ($query) {
            $query->where('name', 'Mantenimiento');
        })->get();

        $this->assertCount(3, $maintenanceOrdersQuery);

        // Test eager loading
        $ordersWithCategories = Order::with('categories')->get();
        foreach ($ordersWithCategories as $order) {
            $this->assertNotNull($order->categories);
            $this->assertTrue($order->relationLoaded('categories'));
        }
    }

    #[Test]
    public function it_checks_if_orders_can_exist_without_category(): void
    {
        $user = User::factory()->create();

        $order = Order::factory()->createQuietly([
            'customer_id' => $user->id,
        ]);

        $this->assertCount(0, $order->categories);
        $this->assertDatabaseMissing('order_category', [
            'order_id' => $order->id,
        ]);
    }

    #[Test]
    public function it_checks_if_deleting_category_detaches_from_orders(): void
    {
        $category = Category::factory()->create();
        $order = Order::factory()->createQuietly();
        $order->categories()->attach($category->id);

        // Verify initial state
        $this->assertCount(1, $order->fresh()->categories);

        // Delete category
        $category->delete();

        // Refresh order and verify category is detached
        $order->refresh();
        $this->assertCount(0, $order->categories);
    }
}
