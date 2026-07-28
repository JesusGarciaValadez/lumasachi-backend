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
    Feature::flushCache();
});
test('engine options caches by locale and item type', function () {
    Feature::define(MotorItems::class, true);

    // Seed a minimal engine_block service
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

    // First call should be MISS
    $first = $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    $first->assertOk();
    $first->assertHeader('X-Cache', 'MISS');

    // Second call should be HIT
    $second = $this->withHeaders(['Accept-Language' => 'en'])
        ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    $second->assertOk();
    $second->assertHeader('X-Cache', 'HIT');

    // Different locale should be a MISS key
    $third = $this->withHeaders(['Accept-Language' => 'es'])
        ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    $third->assertOk();
    $third->assertHeader('X-Cache', 'MISS');

    $regional = $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
        ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    $regional->assertOk();
    $regional->assertHeader('X-Cache', 'HIT');

    $regionalSpanish = $this->withHeaders(['Accept-Language' => 'es-MX'])
        ->getJson('/api/v1/catalog/engine-options?item_type=engine_block');
    $regionalSpanish->assertOk();
    $regionalSpanish->assertHeader('X-Cache', 'HIT');
});
