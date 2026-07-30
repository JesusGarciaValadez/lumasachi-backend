<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\OrderRefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOrderRefund
 */
final class OrderRefund extends Model
{
    /** @use HasFactory<OrderRefundFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'source_payment_id',
        'amount',
        'status',
        'reason',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'processed_by',
        'processed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'uuid' => 'string',
        'amount' => 'decimal:2',
        'status' => RefundStatus::class,
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderPayment, $this> */
    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'source_payment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /** @return Factory<static> */
    protected static function newFactory(): Factory
    {
        return OrderRefundFactory::new();
    }
}
