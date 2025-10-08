<?php

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\OrderItemType;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

final class CatalogSeederIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    public function test_seeded_services_are_returned_with_i18n_en(): void
    {
        $this->seed(ServiceCatalogSeeder::class);
        Feature::define(\App\Features\MotorItems::class, true);

        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->actingAs($employee);

        $response = $this->withHeaders(['Accept-Language' => 'en'])
            ->getJson('/api/v1/catalog/engine-options?item_type=' . OrderItemType::ENGINE_BLOCK->value);

        $response->assertOk();
        $service = collect($response->json('services'))
            ->firstWhere('service_key', 'wash_block');
        $this->assertNotNull($service);
        $this->assertSame('Wash engine block', $service['service_name']);
        $this->assertSame('600.00', $service['base_price']);
        $this->assertSame('696.00', $service['net_price']); // 600 * 1.16
    }

    public function test_seeded_services_are_returned_with_i18n_es_and_sorted(): void
    {
        $this->seed(ServiceCatalogSeeder::class);
        Feature::define(\App\Features\MotorItems::class, true);

        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->actingAs($employee);

        $response = $this->withHeaders(['Accept-Language' => 'es'])
            ->getJson('/api/v1/catalog/engine-options?item_type=' . OrderItemType::ENGINE_BLOCK->value);

        $response->assertOk();
        $services = $response->json('services');
        $this->assertSame('Lavado de bloque', $services[0]['service_name']); // display_order = 1
        $this->assertSame('Bruñido de cilindros', $services[1]['service_name']); // display_order = 2
        $this->assertTrue($services[1]['requires_measurement']);
    }
}
