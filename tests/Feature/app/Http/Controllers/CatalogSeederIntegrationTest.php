<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Enums\UserRole;
use App\Features\MotorItems;
use App\Models\ServiceCatalog;
use App\Models\User;
use Database\Seeders\ServiceCatalogSeeder;
use Laravel\Pennant\Feature;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Feature::flushCache();
});
test('seeded services are returned with i18n en', function () {
    $this->seed(ServiceCatalogSeeder::class);
    Feature::define(MotorItems::class, true);

    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->actingAs($employee);

    $response = $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/catalog/engine-options?item_type=' . OrderItemType::EngineBlock->value);

    $response->assertOk();
    $service = collect($response->json('services'))
        ->firstWhere('service_key', 'wash_block');
    expect($service)->not->toBeNull();
    expect($service['service_name'])->toBe('Engine block wash');
    expect($service['base_price'])->toBe('600.00');
    expect($service['net_price'])->toBe('696.00');
    // 600 * 1.16
});
test('seeded services are returned with i18n es and sorted', function () {
    $this->seed(ServiceCatalogSeeder::class);
    Feature::define(MotorItems::class, true);

    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->actingAs($employee);

    $response = $this->withHeaders(['Accept-Language' => 'es'])
        ->getJson('/api/v1/catalog/engine-options?item_type=' . OrderItemType::EngineBlock->value);

    $response->assertOk();
    $services = $response->json('services');
    expect($services[0]['service_name'])->toBe('Lavado de block');
    // display_order = 1
    expect($services[1]['service_name'])->toBe('Rectificado por cilindro (P.U.)');
    // display_order = 2
    expect($services[1]['requires_measurement'])->toBeTrue();
});
test('every active seeded service has both locale translations', function () {
    $this->seed(ServiceCatalogSeeder::class);

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        foreach (ServiceCatalog::active()->get() as $service) {
            $this->assertNotSame($service->service_name_key, $service->service_name);
        }
    }
});
