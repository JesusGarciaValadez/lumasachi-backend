<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use App\Enums\UserRole;
use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Models\Attachment;
use App\Models\OrderHistory;
use App\Observers\OrderObserver;
use App\Traits\HasAttachments;
use Database\Factories\OrderFactory;

/**
 * @mixin IdeHelperOrder
 */
#[ObservedBy([OrderObserver::class])]
final class Order extends Model
{
    use HasFactory, HasUuids, HasAttachments;

    protected $fillable = [
        'customer_id',
        'title',
        'description',
        'status',
        'priority',
        'category_id',
        'estimated_completion',
        'actual_completion',
        'notes',
        'created_by',
        'updated_by',
        'assigned_to'
    ];

    protected $casts = [
        'estimated_completion' => 'datetime',
        'actual_completion' => 'datetime',
        'priority' => OrderPriority::class,
        'status' => OrderStatus::class,
    ];

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }
}
