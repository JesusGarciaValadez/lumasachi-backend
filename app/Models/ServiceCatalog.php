<?php

namespace App\Models;

use App\Enums\OrderItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

final class ServiceCatalog extends Model
{
    use HasFactory;

    protected $table = 'service_catalog';

    protected $fillable = [
        'service_key',
        'service_name_key',
        'item_type',
        'base_price',
        'tax_percentage',
        'requires_measurement',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'requires_measurement' => 'boolean',
            'is_active' => 'boolean',
            'item_type' => OrderItemType::class,
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForItemType($query, OrderItemType $type)
    {
        return $query->where('item_type', $type->value);
    }

    public function getServiceNameAttribute(): string
    {
        $key = $this->service_name_key;
        $translated = __($key);
        return is_string($translated) && $translated !== $key ? $translated : ($this->attributes['service_key'] ?? $this->service_key);
    }

    public function getNetPriceAttribute(): float
    {
        $base = (float) $this->base_price;
        $tax = (float) $this->tax_percentage;
        return round($base * (1 + ($tax / 100)), 2);
    }
}
