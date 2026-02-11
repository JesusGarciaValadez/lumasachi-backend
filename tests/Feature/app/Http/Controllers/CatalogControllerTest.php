<?php

declare(strict_types=1);

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\UserRole;
use App\Features\MotorItems;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure Pennant uses array store in tests if configured via phpunit.xml (optional)
        Feature::flushCache();
    }

    public function test_returns_404_when_feature_disabled(): void
    {
        Feature::define(MotorItems::class, false);

        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
        $response->assertStatus(404);
    }

    public function test_admin_and_super_admin_can_access_catalog(): void
    {
        Feature::define(MotorItems::class, true);

        $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
        Sanctum::actingAs($admin);
        $responseAdmin = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
        $this->assertTrue(in_array($responseAdmin->status(), [200, 422])); // 422 if no data, 200 if valid

        $super = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value]);
        Sanctum::actingAs($super);
        $responseSuper = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
        $this->assertTrue(in_array($responseSuper->status(), [200, 422]));
    }

    public function test_returns_422_on_invalid_item_type(): void
    {
        Feature::define(MotorItems::class, true);
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        Sanctum::actingAs($employee);

        $response = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/catalog/engine-options?item_type=invalid_type');
        $response->assertStatus(422);
    }

    public function test_returns_full_catalog_structure_without_item_type(): void
    {
        Feature::define(MotorItems::class, true);

        // Seed one service to ensure data appears
        DB::table('service_catalog')->insert([
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'service_key' => 'wash_block',
                'service_name_key' => 'services.wash_block',
                'item_type' => 'engine_block',
                'base_price' => 600.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        Sanctum::actingAs($employee);

        $response = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/catalog/engine-options');
        $response->assertOk();
        $response->assertJsonStructure([
            'item_types' => [['key', 'label']],
            'components_by_type',
            'services_by_type',
        ]);
    }

    public function test_employee_can_fetch_engine_block_catalog_in_spanish(): void
    {
        Feature::define(MotorItems::class, true);

        // Seed minimal catalog for engine_block
        DB::table('service_catalog')->insert([
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'service_key' => 'wash_block',
                'service_name_key' => 'services.wash_block',
                'item_type' => 'engine_block',
                'base_price' => 600.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $employee = User::factory()->create([
            'role' => UserRole::EMPLOYEE->value,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->withHeaders(['Accept-Language' => 'es'])
            ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'item_type',
            'item_type_label',
            'components' => [
                ['key', 'label'],
            ],
            'services' => [
                ['service_key', 'service_name', 'base_price', 'net_price', 'requires_measurement', 'display_order', 'item_type'],
            ],
        ]);

        $data = $response->json();
        $this->assertSame('engine_block', $data['item_type']);
        // Service sample must be present
        $this->assertTrue(collect($data['services'])->contains(fn ($s) => $s['service_key'] === 'wash_block'));
    }

    public function test_customer_cannot_access_catalog_endpoint(): void
    {
        Feature::define(MotorItems::class, true);

        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');

        $response->assertStatus(403);
    }
}
