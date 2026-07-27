<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case SPANISH = 'es';
    case ENGLISH = 'en';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function normalize(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(mb_trim($value));
        $normalized = str_replace('_', '-', $normalized);
        $primaryTag = explode('-', $normalized, 2)[0] ?? '';

        return self::tryFrom($primaryTag);
    }
}
