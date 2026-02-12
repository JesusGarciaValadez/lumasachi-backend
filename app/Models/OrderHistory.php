<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use App\Observers\OrderHistoryObserver;
use App\Traits\HasAttachments;
use BackedEnum;
use Database\Factories\OrderHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperOrderHistory
 */
#[ObservedBy([OrderHistoryObserver::class])]
final class OrderHistory extends Model
{
    use HasAttachments, HasFactory, HasUuids;

    /**
     * Available fields that can be tracked
     */
    public const FIELD_STATUS = 'status';

    public const FIELD_PRIORITY = 'priority';

    public const FIELD_ASSIGNED_TO = 'assigned_to';

    public const FIELD_TITLE = 'title';

    public const FIELD_ESTIMATED_COMPLETION = 'estimated_completion';

    public const FIELD_ACTUAL_COMPLETION = 'actual_completion';

    public const FIELD_NOTES = 'notes';

    // Extended fields for item/service tracking
    public const FIELD_ITEM_RECEIVED = 'item_received';

    public const FIELD_ITEM_COMPONENT_RECEIVED = 'item_component_received';

    public const FIELD_SERVICE_BUDGETED = 'service_budgeted';

    public const FIELD_SERVICE_AUTHORIZED = 'service_authorized';

    public const FIELD_SERVICE_COMPLETED = 'service_completed';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The name of the primary key.
     *
     * @var string
     */
    protected $keyName = 'id';

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'order_id',
        'field_changed',
        'old_value',
        'new_value',
        'comment',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uuid' => 'string',
        // Dynamic casting will be handled by accessors/mutators
    ];

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

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
     * Get a human-readable description of the change
     */
    public function getDescriptionAttribute(): string
    {
        $field = str_replace('_', ' ', $this->field_changed);
        $oldValue = $this->getFormattedValue($this->field_changed, $this->old_value);
        $newValue = $this->getFormattedValue($this->field_changed, $this->new_value);

        if (is_null($this->old_value)) {
            return ucfirst($field)." set to: {$newValue}";
        }

        if (is_null($this->new_value)) {
            return ucfirst($field)." removed (was: {$oldValue})";
        }

        return ucfirst($field)." changed from {$oldValue} to {$newValue}";
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the order(s) related to this order history.
     * This is an alias for the order() method to maintain compatibility.
     *
     * DEPRECATED: Use order() instead. Returns a single Order, not a collection.
     * Maintained for backward compatibility only.
     */
    public function orders(): BelongsTo
    {
        return $this->order();
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): OrderHistoryFactory
    {
        return OrderHistoryFactory::new();
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
            case self::FIELD_ITEM_RECEIVED:
            case self::FIELD_ITEM_COMPONENT_RECEIVED:
            case self::FIELD_SERVICE_BUDGETED:
            case self::FIELD_SERVICE_AUTHORIZED:
            case self::FIELD_SERVICE_COMPLETED:
                // normalize common boolean-like values
                if (is_string($value)) {
                    $lower = mb_strtolower($value);
                    if (in_array($lower, ['true', '1', 'yes'], true)) {
                        return true;
                    }
                    if (in_array($lower, ['false', '0', 'no'], true)) {
                        return false;
                    }
                }

                return (bool) $value;
            default:
                return $value;
        }
    }

    /**
     * Serialize field value for storage
     */
    protected function serializeFieldValue(string $field, mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->toISOString();
        }

        return (string) $value;
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

        if ($castedValue instanceof BackedEnum) {
            return method_exists($castedValue, 'getLabel') ? $castedValue->getLabel() : $castedValue->value;
        }

        if ($castedValue instanceof \Carbon\Carbon) {
            return $castedValue->format('Y-m-d H:i');
        }

        return (string) $castedValue;
    }
}
