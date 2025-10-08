<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OrderService extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_item_id',
        'service_key',
        'measurement',
        'is_budgeted',
        'is_authorized',
        'is_completed',
        'notes',
        'base_price',
        'net_price',
    ];

    protected function casts(): array
    {
        return [
            'is_budgeted' => 'boolean',
            'is_authorized' => 'boolean',
            'is_completed' => 'boolean',
            'base_price' => 'decimal:2',
            'net_price' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_key', 'service_key');
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
