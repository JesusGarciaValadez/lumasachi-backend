<?php

declare(strict_types=1);

use App\Enums\OrderItemType;
use App\Models\ServiceCatalog;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks scopes and net price and translation fallback', function () {
    // Inactive record should be filtered out by scope
    ServiceCatalog::create([
        'service_key' => 'wash_block',
        'service_name_key' => 'services.wash_block',
        'item_type' => OrderItemType::EngineBlock,
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
        'item_type' => OrderItemType::EngineBlock,
        'base_price' => 600.00,
        'tax_percentage' => 16.00,
        'requires_measurement' => false,
        'is_active' => true,
        'display_order' => 2,
    ]);

    $records = ServiceCatalog::active()->forItemType(OrderItemType::EngineBlock)->get();
    expect($records)->toHaveCount(1);
    expect($records->first()->service_key)->toBe('wash_block_active');

    // Net price calculation (IVA 16%)
    expect($records->first()->net_price)->toBe(696.00);

    app()->setLocale('es');
    expect($active->service_name)->toBe('Servicio no disponible');

    app()->setLocale('en');
    expect($active->service_name)->toBe('Service unavailable');
});
