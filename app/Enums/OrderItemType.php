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
}
