<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderItemType;
use App\Observers\OrderItemObserver;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperOrderItem
 */
#[ObservedBy([OrderItemObserver::class])]
final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'item_type',
        'is_received',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<OrderItemComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class);
    }

    /** @return HasMany<OrderService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(OrderService::class);
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
            'item_type' => OrderItemType::class,
        ];
    }
}
