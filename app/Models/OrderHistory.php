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

    public const FIELD_CATEGORIES = 'categories';

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

        if ($this->field_changed === self::FIELD_CATEGORIES) {
            $oldCategoryNames = $this->getCategoryNames($this->old_value);
            $newCategoryNames = $this->getCategoryNames($this->new_value);

            if ($oldCategoryNames === $newCategoryNames) {
                return 'Categories unchanged';
            }
            if (empty($oldCategoryNames)) {
                return 'Categories added: '.implode(', ', $newCategoryNames);
            }
            if (empty($newCategoryNames)) {
                return 'Categories removed (was: '.implode(', ', $oldCategoryNames).')';
            }
            $added = array_values(array_diff($newCategoryNames, $oldCategoryNames));
            $removed = array_values(array_diff($oldCategoryNames, $newCategoryNames));
            $changes = [];
            if (! empty($added)) {
                $changes[] = 'added: '.implode(', ', $added);
            }
            if (! empty($removed)) {
                $changes[] = 'removed: '.implode(', ', $removed);
            }

            return 'Categories updated ('.implode('; ', $changes).')';
        }

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
    protected static function newFactory()
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
            case self::FIELD_CATEGORIES:
                $ids = match (true) {
                    is_string($value) => json_decode($value, true) ?: [],
                    $value instanceof \Illuminate\Support\Collection => $value->all(),
                    is_array($value) => $value,
                    default => [],
                };
                $ids = array_values(array_unique(array_map('intval', $ids)));
                sort($ids);

                return json_encode($ids);
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

        if ($value instanceof \Carbon\Carbon) {
            return $value->toISOString();
        }

        if ($field === self::FIELD_CATEGORIES) {
            // Normalize to an array of integer IDs
            if (is_string($value)) {
                $value = json_decode($value, true) ?: [];
            }
            if ($value instanceof \Illuminate\Support\Collection) {
                $value = $value->all();
            }
            $ids = array_map(
                fn ($item) => is_object($item) ? (int) ($item->id ?? 0) : (int) $item,
                (array) $value
            );
            $ids = array_values(array_unique(array_filter($ids)));
            sort($ids);

            return json_encode($ids);
        }

        return (string) $value;
    }

    /**
     * Get category names from a JSON string of category IDs.
     */
    protected function getCategoryNames(?string $value): array
    {
        if (is_null($value)) {
            return [];
        }

        $categoryIds = is_string($value) ? json_decode($value, true) : (array) $value;
        if (! is_array($categoryIds)) {
            return [];
        }

        $categories = Category::whereIn('id', $categoryIds)->pluck('name')->toArray();

        return $categories;
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
