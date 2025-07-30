<?php

namespace Modules\Lumasachi\app\Enums;

enum UserType: string
{
    case INDIVIDUAL = 'Individual';
    case BUSINESS = 'Business';

    public static function getTypes(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Individual',
            self::BUSINESS => 'Business'
        };
    }
}
