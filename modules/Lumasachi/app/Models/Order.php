<?php

namespace Modules\Lumasachi\app\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Traits\HasAttachments;

final class Order extends Model
{
    use HasFactory, HasUuids, HasAttachments;
    protected $fillable = [
        'customer_id',
        'title',
        'description',
        'status',
        'priority',
        'category',
        'estimated_completion',
        'actual_completion',
        'notes',
        'created_by',
        'updated_by',
        'assigned_to'
    ];

    protected $casts = [
        'estimated_completion' => 'datetime',
        'actual_completion' => 'datetime'
    ];

    // Enums
    const STATUS_OPEN = 'Open';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_READY_FOR_DELIVERY = 'Ready for delivery';
    const STATUS_DELIVERED = 'Delivered';
    const STATUS_PAID = 'Paid';
    const STATUS_RETURNED = 'Returned';
    const STATUS_NOT_PAID = 'Not paid';
    const STATUS_CANCELLED = 'Cancelled';

    const PRIORITY_LOW = 'Low';
    const PRIORITY_NORMAL = 'Normal';
    const PRIORITY_HIGH = 'High';
    const PRIORITY_URGENT = 'Urgent';

    // Relationships - Updated for unified architecture
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id')
            ->where('role', UserRole::CUSTOMER);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to')
            ->where('role', UserRole::EMPLOYEE);
    }

    public function orderHistories()
    {
        return $this->hasMany(OrderHistory::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory()
    {
        return \Modules\Lumasachi\database\factories\OrderFactory::new();
    }
}
