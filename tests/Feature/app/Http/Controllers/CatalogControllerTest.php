<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\MotorItems;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Ensure Pennant uses array store in tests if configured via phpunit.xml (optional)
    Feature::flushCache();
});
test('returns 404 when feature disabled', function () {
    Feature::define(MotorItems::class, false);

    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    Sanctum::actingAs($employee);

    $response = $this->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    $response->assertStatus(404);
});
test('admin and super admin can access catalog', function () {
    Feature::define(MotorItems::class, true);

    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATOR->value]);
    Sanctum::actingAs($admin);
    $responseAdmin = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    expect(in_array($responseAdmin->status(), [200, 422]))->toBeTrue();

    // 422 if no data, 200 if valid
    $super = User::factory()->create(['role' => UserRole::SUPER_ADMINISTRATOR->value]);
    Sanctum::actingAs($super);
    $responseSuper = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    expect(in_array($responseSuper->status(), [200, 422]))->toBeTrue();
});
test('returns 422 on invalid item type', function () {
    Feature::define(MotorItems::class, true);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    Sanctum::actingAs($employee);

    $response = $this->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/catalog/engine-options?item_type=invalid_type');
    $response->assertStatus(422);
});
test('returns full catalog structure without item type', function () {
    Feature::define(MotorItems::class, true);

    // Seed one service to ensure data appears
    DB::table('service_catalog')->insert([
        [
            'uuid' => Str::uuid()->toString(),
            'service_key' => 'wash_block',
            'service_name_key' => 'service_catalog.wash_block',
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
});
test('employee can fetch engine block catalog in spanish', function () {
    Feature::define(MotorItems::class, true);

    // Seed minimal catalog for engine_block
    DB::table('service_catalog')->insert([
        [
            'uuid' => Str::uuid()->toString(),
            'service_key' => 'wash_block',
            'service_name_key' => 'service_catalog.wash_block',
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
    expect($data['item_type'])->toBe('engine_block');
    expect($data['item_type_label'])->toBe('Block');
    expect(collect($data['components'])->firstWhere('key', 'camshaft')['label'])->toBe('Árbol de levas');

    // Service sample must be present
    expect(collect($data['services'])->firstWhere('service_key', 'wash_block')['service_name'])->toBe('Lavado de block');
});
test('rejects an explicit unsupported locale', function () {
    Feature::define(MotorItems::class, true);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    Sanctum::actingAs($employee);

    $this->getJson('/api/v1/catalog/engine-options?locale=fr')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['locale']);
});
test('customer cannot access catalog endpoint', function () {
    Feature::define(MotorItems::class, true);

    $customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);

    Sanctum::actingAs($customer);

    $response = $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');

    $response->assertStatus(403);
});
