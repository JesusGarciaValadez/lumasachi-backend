<?php

namespace Tests\Unit\app\Models;

use App\Enums\OrderItemType;
use App\Models\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_scopes_and_net_price_and_translation_fallback(): void
    {
        // Inactive record should be filtered out by scope
        ServiceCatalog::create([
            'service_key' => 'wash_block',
            'service_name_key' => 'services.wash_block',
            'item_type' => OrderItemType::ENGINE_BLOCK,
            'base_price' => 600.00,
            'tax_percentage' => 16.00,
            'requires_measurement' => false,
            'is_active' => false,
            'display_order' => 1,
        ]);

        // Active record will be returned
        $active = ServiceCatalog::create([
            'service_key' => 'wash_block_active',
            'service_name_key' => 'services.wash_block', // likely missing translation -> fallback to service_key
            'item_type' => OrderItemType::ENGINE_BLOCK,
            'base_price' => 600.00,
            'tax_percentage' => 16.00,
            'requires_measurement' => false,
            'is_active' => true,
            'display_order' => 2,
        ]);

        $records = ServiceCatalog::active()->forItemType(OrderItemType::ENGINE_BLOCK)->get();
        $this->assertCount(1, $records);
        $this->assertEquals('wash_block_active', $records->first()->service_key);

        // Net price calculation (IVA 16%)
        $this->assertSame(696.00, $records->first()->net_price);

        // Translation fallback (no lang file loaded), should return service_key
        $this->assertSame('wash_block_active', $active->service_name);
    }
}
