<?php

namespace App\Models;

use App\Enums\OrderItemType;
use App\Observers\OrderItemObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([OrderItemObserver::class])]
final class OrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'item_type',
        'is_received',
    ];

    protected function casts(): array
    {
        return [
            'is_received' => 'boolean',
            'item_type' => OrderItemType::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(OrderItemComponent::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(OrderService::class);
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
