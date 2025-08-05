<?php

namespace Modules\Lumasachi\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\User;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;
use Modules\Lumasachi\app\Traits\HasAttachments;

class OrderHistory extends Model
{
    use HasFactory, HasUuids, HasAttachments;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory()
    {
        return \Modules\Lumasachi\database\factories\OrderHistoryFactory::new();
    }

    protected $fillable = [
        'order_id',
        'status_from',
        'status_to',
        'priority_from',
        'priority_to',
        'description',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'status_from' => OrderStatus::class,
        'status_to' => OrderStatus::class,
        'priority_from' => OrderPriority::class,
        'priority_to' => OrderPriority::class,
    ];

    public function order(): BelongsTo {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the order(s) related to this order history.
     * This is an alias for the order() method to maintain compatibility.
     *
     * @return BelongsTo
     */
    public function orders(): BelongsTo {
        return $this->order();
    }
}
