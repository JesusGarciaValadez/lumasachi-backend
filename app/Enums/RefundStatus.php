<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundStatus: string
{
    case Requested = 'Requested';
    case Approved = 'Approved';
    case Processed = 'Processed';
    case Rejected = 'Rejected';
}
