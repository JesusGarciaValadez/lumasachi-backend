<?php

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\UserRole;
use App\Features\MotorItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Feature::flushCache();
    }

    public function test_engine_options_caches_by_locale_and_item_type(): void
    {
        Feature::define(MotorItems::class, true);

        // Seed a minimal engine_block service
        DB::table('service_catalog')->insert([
            [
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
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

        $employee = \App\Models\User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
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
    }
}
