<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemComponentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOrderItemComponent
 */
final class OrderItemComponent extends Model
{
    /** @use HasFactory<OrderItemComponentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_item_id',
        'component_name',
        'is_received',
    ];

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'is_received' => 'boolean',
        ];
    }
}
