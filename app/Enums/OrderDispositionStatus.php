<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderDispositionStatus: string
{
    case Returned = 'Returned';
    case Cancelled = 'Cancelled';

    public function getLabel(): string
    {
        return __('orders.status_labels.' . $this->value);
    }
}
