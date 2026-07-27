<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderPriority: string
{
    case LOW = 'Low';
    case NORMAL = 'Normal';
    case HIGH = 'High';
    case URGENT = 'Urgent';

    /**
     * @return list<string>
     */
    public static function getPriorities(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        $key = 'orders.priority_labels.' . $this->value;
        $translated = __($key);

        return is_string($translated) && $translated !== $key
            ? $translated
            : __('motor.fallback');
    }
}
