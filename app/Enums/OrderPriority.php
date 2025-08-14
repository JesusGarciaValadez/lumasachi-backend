<?php

namespace App\Enums;

enum OrderPriority: string
{
    case LOW = 'Low';
    case NORMAL = 'Normal';
    case HIGH = 'High';
    case URGENT = 'Urgent';

    public static function getPriorities(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::URGENT => 'Urgent'
        };
    }
}
