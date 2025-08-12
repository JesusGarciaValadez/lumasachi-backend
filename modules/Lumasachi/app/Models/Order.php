<?php

namespace Modules\Lumasachi\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Lumasachi\database\factories\OrderFactory;

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
        return \Modules\Lumasachi\database\factories\OrderFactory::new();
    }
}
