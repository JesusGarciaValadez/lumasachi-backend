<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderMotorInfo extends Model
{
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'down_payment' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'is_fully_paid' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float)$this->total_cost - (float)$this->down_payment);
    }
}
