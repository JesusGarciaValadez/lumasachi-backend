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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin IdeHelperOrder
 */
#[ObservedBy([OrderObserver::class])]
final class Order extends Model
{
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
     * @var array<int, string>
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
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // Relationships - Updated for unified architecture
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->where('role', UserRole::CUSTOMER->value);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function orderHistories(): HasMany
    {
        return $this->hasMany(OrderHistory::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'order_category')->withTimestamps();
    }

    public function motorInfo(): HasOne
    {
        return $this->hasOne(OrderMotorInfo::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function services(): HasManyThrough
    {
        return $this->hasManyThrough(OrderService::class, OrderItem::class, 'order_id', 'order_item_id');
    }

    /**
     * Recalculate liquidation totals based on completed services.
     * - total_cost = sum of net_price for completed services
     * - is_fully_paid = total_cost <= down_payment
     */
    public function recalculateTotals(): void
    {
        $services = $this->relationLoaded('services')
            ? $this->services
            : $this->services()->get(['net_price', 'is_completed']);

        $total = (float) $services
            ->where('is_completed', true)
            ->sum(fn ($service) => (float) $service->net_price);

        $info = $this->motorInfo()->firstOrNew();

        if (! $info->exists && $info->down_payment === null) {
            $info->down_payment = 0;
        }

        $info->total_cost = $total;
        $info->is_fully_paid = bccomp((string) ($info->down_payment ?? 0), (string) $total, 2) >= 0;
        $info->save();
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
