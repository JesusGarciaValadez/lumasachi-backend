<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCatalog;
use App\Enums\OrderItemType;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Engine block
            [
                'service_key' => 'wash_block',
'service_name_key' => 'service_catalog.wash_block',
                'item_type' => OrderItemType::ENGINE_BLOCK->value,
                'base_price' => 600.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'service_key' => 'hone_cylinders',
'service_name_key' => 'service_catalog.hone_cylinders',
                'item_type' => OrderItemType::ENGINE_BLOCK->value,
                'base_price' => 800.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => true,
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'service_key' => 'deck_block',
'service_name_key' => 'service_catalog.deck_block',
                'item_type' => OrderItemType::ENGINE_BLOCK->value,
                'base_price' => 950.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 3,
            ],

            // Cylinder head
            [
                'service_key' => 'pressure_test_head',
'service_name_key' => 'service_catalog.pressure_test_head',
                'item_type' => OrderItemType::CYLINDER_HEAD->value,
                'base_price' => 450.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'service_key' => 'seat_cut',
'service_name_key' => 'service_catalog.seat_cut',
                'item_type' => OrderItemType::CYLINDER_HEAD->value,
                'base_price' => 500.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => true,
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'service_key' => 'valve_grinding',
'service_name_key' => 'service_catalog.valve_grinding',
                'item_type' => OrderItemType::CYLINDER_HEAD->value,
                'base_price' => 550.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 3,
            ],

            // Crankshaft
            [
                'service_key' => 'grind_crankshaft',
'service_name_key' => 'service_catalog.grind_crankshaft',
                'item_type' => OrderItemType::CRANKSHAFT->value,
                'base_price' => 700.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => true,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'service_key' => 'polish_crankshaft',
'service_name_key' => 'service_catalog.polish_crankshaft',
                'item_type' => OrderItemType::CRANKSHAFT->value,
                'base_price' => 350.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'service_key' => 'balance_crankshaft',
'service_name_key' => 'service_catalog.balance_crankshaft',
                'item_type' => OrderItemType::CRANKSHAFT->value,
                'base_price' => 900.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 3,
            ],

            // Connecting rods
            [
                'service_key' => 'resize_rods',
'service_name_key' => 'service_catalog.resize_rods',
                'item_type' => OrderItemType::CONNECTING_RODS->value,
                'base_price' => 400.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'service_key' => 'press_pistons',
'service_name_key' => 'service_catalog.press_pistons',
                'item_type' => OrderItemType::CONNECTING_RODS->value,
                'base_price' => 300.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => false,
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'service_key' => 'install_bushings',
'service_name_key' => 'service_catalog.install_bushings',
                'item_type' => OrderItemType::CONNECTING_RODS->value,
                'base_price' => 320.00,
                'tax_percentage' => 16.00,
                'requires_measurement' => true,
                'is_active' => true,
                'display_order' => 3,
            ],
        ];

        foreach ($rows as $row) {
            ServiceCatalog::query()->updateOrCreate(
                ['service_key' => $row['service_key']],
                $row
            );
        }
    }
}
