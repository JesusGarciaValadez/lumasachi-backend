<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Observers\OrderObserver;
use App\Traits\HasAttachments;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin IdeHelperOrder
 */
#[ObservedBy([OrderObserver::class])]
final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasAttachments, HasFactory, HasUuids;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The name of the primary key.
     *
     * @var string
     */
    protected $keyName = 'id';

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'title',
        'description',
        'status',
        'priority',
        'estimated_completion',
        'actual_completion',
        'notes',
        'created_by',
        'updated_by',
        'assigned_to',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uuid' => 'string',
        'estimated_completion' => 'datetime',
        'actual_completion' => 'datetime',
        'priority' => OrderPriority::class,
        'status' => OrderStatus::class,
    ];

    /**
     * Get the columns that should receive a unique identifier.
     */
    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // Relationships - Updated for unified architecture

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->where('role', UserRole::CUSTOMER->value);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<OrderHistory, $this> */
    public function orderHistories(): HasMany
    {
        return $this->hasMany(OrderHistory::class);
    }

    /** @return HasMany<OrderPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class, 'order_id');
    }

    /** @return HasMany<OrderRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class, 'order_id');
    }

    /** @return HasOne<OrderMotorInfo, $this> */
    public function motorInfo(): HasOne
    {
        return $this->hasOne(OrderMotorInfo::class, 'order_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /** @return HasManyThrough<OrderService, OrderItem, $this> */
    public function services(): HasManyThrough
    {
        return $this->hasManyThrough(OrderService::class, OrderItem::class, 'order_id', 'order_item_id');
    }

    public function completedTotal(): string
    {
        $services = $this->relationLoaded('services')
            ? $this->services
            : $this->services()->get(['net_price', 'is_completed']);

        return $services
            ->where('is_completed', true)
            ->reduce(
                fn(string $total, OrderService $service): string => bcadd($total, (string)$service->net_price, 2),
                '0.00'
            );
    }

    public function totalPaid(): string
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get(['amount']);

        if ($payments->isNotEmpty()) {
            return $payments->reduce(
                fn(string $total, OrderPayment $payment): string => bcadd($total, (string)$payment->amount, 2),
                '0.00'
            );
        }

        return '0.00';
    }

    public function paymentStatus(): string
    {
        $paid = $this->totalPaid();
        $completed = $this->completedTotal();

        if (bccomp($paid, '0.00', 2) <= 0) {
            return 'Unpaid';
        }

        return bccomp($paid, $completed, 2) >= 0 ? 'Paid' : 'Partially Paid';
    }

    public function hasPendingPayment(): bool
    {
        return bccomp($this->completedTotal(), $this->totalPaid(), 2) === 1;
    }

    /**
     * Calculate lifecycle totals from persisted services and payment state.
     *
     * @return array{budgeted: string, budgeted_base: string, budgeted_net: string, authorized: string, completed: string, advance_payment: string, paid: string, payment_status: string, remaining_balance: string}
     */
    public function financialTotals(): array
    {
        $sum = function (string $field, string $priceField = 'net_price'): string {
            return number_format(
                (float)$this->services()->where("order_services.{$field}", true)->sum("order_services.{$priceField}"),
                2,
                '.',
                ''
            );
        };

        $completed = $this->completedTotal();
        $paid = $this->totalPaid();

        return [
            'budgeted' => $sum('is_budgeted'),
            'budgeted_base' => $sum('is_budgeted', 'base_price'),
            'budgeted_net' => $sum('is_budgeted', 'net_price'),
            'authorized' => $sum('is_authorized'),
            'completed' => $completed,
            'advance_payment' => $paid,
            'paid' => $paid,
            'payment_status' => $this->paymentStatus(),
            'remaining_balance' => bccomp($completed, $paid, 2) === 1
                ? bcsub($completed, $paid, 2)
                : '0.00',
        ];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }
}
