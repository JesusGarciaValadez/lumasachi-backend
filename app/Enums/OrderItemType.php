<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderItemType: string
{
    case CylinderHead = 'cylinder_head';
    case EngineBlock = 'engine_block';
    case Crankshaft = 'crankshaft';
    case ConnectingRods = 'connecting_rods';
    case Others = 'others';

    /**
     * @return array<string>
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::CylinderHead => 'Cylinder Head',
            self::EngineBlock => 'Engine Block',
            self::Crankshaft => 'Crankshaft',
            self::ConnectingRods => 'Connecting Rods',
            self::Others => 'Others',
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
            self::CylinderHead => [
                'camshaft_covers',
                'bolts',
                'rocker_arm_shaft',
                'wedges',
                'springs',
                'shims',
                'valves',
                'guides',
            ],
            self::EngineBlock => [
                'bearing_caps',
                'cap_bolts',
                'camshaft',
                'guides',
                'bearings',
                'camshaft_key',
                'camshaft_gear',
            ],
            self::Crankshaft => [
                'iron_gear',
                'bronze_gear',
                'lock',
                'key',
                'flywheel',
                'bolt',
                'deflector',
            ],
            self::ConnectingRods => [
                'bolts',
                'nuts',
                'pistons',
                'locks',
                'bearings',
            ],
            self::Others => [
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
