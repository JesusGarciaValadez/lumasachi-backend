<?php

namespace App\Enums;

enum OrderItemType: string
{
    case CYLINDER_HEAD = 'cylinder_head';
    case ENGINE_BLOCK = 'engine_block';
    case CRANKSHAFT = 'crankshaft';
    case CONNECTING_RODS = 'connecting_rods';
    case OTHERS = 'others';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::CYLINDER_HEAD => 'Cylinder Head',
            self::ENGINE_BLOCK => 'Engine Block',
            self::CRANKSHAFT => 'Crankshaft',
            self::CONNECTING_RODS => 'Connecting Rods',
            self::OTHERS => 'Others',
        };
    }

    /**
     * Return the component keys available for this item type.
     * Keys follow the architecture document.
     *
     * @return array<string>
     */
    public function getComponents(): array
    {
        return match ($this) {
            self::CYLINDER_HEAD => [
                'camshaft_covers',
                'bolts',
                'rocker_arm_shaft',
                'wedges',
                'springs',
                'shims',
                'valves',
                'guides',
            ],
            self::ENGINE_BLOCK => [
                'bearing_caps',
                'cap_bolts',
                'camshaft',
                'guides',
                'bearings',
                'camshaft_key',
                'camshaft_gear',
            ],
            self::CRANKSHAFT => [
                'iron_gear',
                'bronze_gear',
                'lock',
                'key',
                'flywheel',
                'bolt',
                'deflector',
            ],
            self::CONNECTING_RODS => [
                'bolts',
                'nuts',
                'pistons',
                'locks',
                'bearings',
            ],
            self::OTHERS => [
                'water_pump',
                'oil_pump',
                'oil_pan',
                'windage_tray',
                'intake_manifold',
                'exhaust_manifold',
                'timing_covers',
            ],
        };
    }
}
