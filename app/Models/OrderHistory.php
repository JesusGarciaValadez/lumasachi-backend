<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\User;
use App\Models\Order;
use App\Models\Attachment;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Traits\HasAttachments;
use Database\Factories\OrderHistoryFactory;

/**
 * @mixin IdeHelperOrderHistory
 */
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
        return OrderHistoryFactory::new();
    }

    protected $fillable = [
        'order_id',
        'field_changed',
        'old_value',
        'new_value',
        'comment',
        'created_by'
    ];

    protected $casts = [
        // Dynamic casting will be handled by accessors/mutators
    ];

    /**
     * Available fields that can be tracked
     */
    const FIELD_STATUS = 'status';
    const FIELD_PRIORITY = 'priority';
    const FIELD_ASSIGNED_TO = 'assigned_to';
    const FIELD_TITLE = 'title';
    const FIELD_ESTIMATED_COMPLETION = 'estimated_completion';
    const FIELD_ACTUAL_COMPLETION = 'actual_completion';
    const FIELD_NOTES = 'notes';
    const FIELD_CATEGORY = 'category_id';

    /**
     * Get the old value with proper casting based on field type
     */
    public function getOldValueAttribute(mixed $value): mixed
    {
        return $this->castFieldValue($this->field_changed, $value, 'old');
    }

    /**
     * Get the new value with proper casting based on field type
     */
    public function getNewValueAttribute(mixed $value): mixed
    {
        return $this->castFieldValue($this->field_changed, $value, 'new');
    }

    /**
     * Cast field value based on field type
     */
    protected function castFieldValue(string $field, mixed $value, string $type = 'new'): mixed
    {
        if (is_null($value)) {
            return null;
        }

        switch ($field) {
            case self::FIELD_STATUS:
                if ($value instanceof OrderStatus) {
                    return $value;
                }
                return $value ? OrderStatus::tryFrom($value) : null;
            case self::FIELD_PRIORITY:
                if ($value instanceof OrderPriority) {
                    return $value;
                }
                return $value ? OrderPriority::tryFrom($value) : null;
            case self::FIELD_ESTIMATED_COMPLETION:
            case self::FIELD_ACTUAL_COMPLETION:
                if ($value instanceof \Carbon\Carbon) {
                    return $value;
                }
                return $value ? \Carbon\Carbon::parse($value) : null;
            default:
                return $value;
        }
    }

    /**
     * Set the old value with proper serialization
     */
    public function setOldValueAttribute(mixed $value): void
    {
        $this->attributes['old_value'] = $this->serializeFieldValue($this->field_changed, $value);
    }

    /**
     * Set the new value with proper serialization
     */
    public function setNewValueAttribute(mixed $value): void
    {
        $this->attributes['new_value'] = $this->serializeFieldValue($this->field_changed, $value);
    }

    /**
     * Serialize field value for storage
     */
    protected function serializeFieldValue(string $field, mixed $value): string|null
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->toISOString();
        }

        return (string) $value;
    }

    /**
     * Get a human-readable description of the change
     */
    public function getDescriptionAttribute(): string
    {
        $field = str_replace('_', ' ', $this->field_changed);
        $oldValue = $this->getFormattedValue($this->field_changed, $this->old_value);
        $newValue = $this->getFormattedValue($this->field_changed, $this->new_value);

        if (is_null($this->old_value)) {
            return ucfirst($field) . " set to: {$newValue}";
        }

        if (is_null($this->new_value)) {
            return ucfirst($field) . " removed (was: {$oldValue})";
        }

        return ucfirst($field) . " changed from {$oldValue} to {$newValue}";
    }

    /**
     * Format value for display
     */
    protected function getFormattedValue(string $field, mixed $value): string
    {
        if (is_null($value)) {
            return 'empty';
        }

        $castedValue = $this->castFieldValue($field, $value);

        if ($castedValue instanceof \BackedEnum) {
            return method_exists($castedValue, 'getLabel') ? $castedValue->getLabel() : $castedValue->value;
        }

        if ($castedValue instanceof \Carbon\Carbon) {
            return $castedValue->format('Y-m-d H:i');
        }

        return (string) $castedValue;
    }

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
