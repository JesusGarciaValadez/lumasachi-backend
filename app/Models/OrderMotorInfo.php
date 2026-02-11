<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\OrderMotorInfoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOrderMotorInfo
 */
#[ObservedBy([OrderMotorInfoObserver::class])]
final class OrderMotorInfo extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_motor_info';

    protected $fillable = [
        'order_id',
        'brand',
        'liters',
        'year',
        'model',
        'cylinder_count',
        'down_payment',
        'total_cost',
        'is_fully_paid',
        'center_torque',
        'rod_torque',
        'first_gap',
        'second_gap',
        'third_gap',
        'center_clearance',
        'rod_clearance',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) $this->total_cost - (float) $this->down_payment);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'down_payment' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'is_fully_paid' => 'boolean',
        ];
    }
}
