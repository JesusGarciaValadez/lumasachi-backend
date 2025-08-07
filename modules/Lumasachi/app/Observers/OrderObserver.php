<?php

namespace Modules\Lumasachi\app\Observers;

use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Illuminate\Support\Facades\Auth;

class OrderObserver
{
    /**
     * Fields to track for changes
     */
    protected array $trackedFields = [
        'status' => OrderHistory::FIELD_STATUS,
        'priority' => OrderHistory::FIELD_PRIORITY,
        'assigned_to' => OrderHistory::FIELD_ASSIGNED_TO,
        'title' => OrderHistory::FIELD_TITLE,
        'notes' => OrderHistory::FIELD_NOTES,
        'estimated_completion' => OrderHistory::FIELD_ESTIMATED_COMPLETION,
        'actual_completion' => OrderHistory::FIELD_ACTUAL_COMPLETION,
        'category_id' => OrderHistory::FIELD_CATEGORY,
    ];

    /**
     * Handle the Order "updating" event.
     */
    public function updating(Order $order): void
    {
        $this->trackChanges($order);
    }

    /**
     * Track changes to the order
     */
    protected function trackChanges(Order $order): void
    {
        $userId = Auth::id();
        if (!$userId) {
            return; // Skip tracking if no authenticated user
        }

        $dirty = $order->getDirty();
        
        foreach ($dirty as $field => $newValue) {
            if (!isset($this->trackedFields[$field])) {
                continue; // Skip untracked fields
            }

            $oldValue = $order->getOriginal($field);
            
            // Skip if values are effectively the same
            if ($this->valuesAreEqual($field, $oldValue, $newValue)) {
                continue;
            }

            OrderHistory::create([
                'order_id' => $order->id,
                'field_changed' => $this->trackedFields[$field],
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * Check if two values are effectively equal
     */
    protected function valuesAreEqual($field, $oldValue, $newValue): bool
    {
        // Handle null comparisons
        if (is_null($oldValue) && is_null($newValue)) {
            return true;
        }

        // Handle date comparisons
        if (in_array($field, ['estimated_completion', 'actual_completion'])) {
            if (is_null($oldValue) || is_null($newValue)) {
                return false;
            }
            
            // Compare dates without microseconds
            $oldDate = $oldValue instanceof \Carbon\Carbon ? $oldValue : \Carbon\Carbon::parse($oldValue);
            $newDate = $newValue instanceof \Carbon\Carbon ? $newValue : \Carbon\Carbon::parse($newValue);
            
            return $oldDate->format('Y-m-d H:i:s') === $newDate->format('Y-m-d H:i:s');
        }

        // For enums, compare the actual values
        if ($oldValue instanceof \BackedEnum) {
            $oldValue = $oldValue->value;
        }
        if ($newValue instanceof \BackedEnum) {
            $newValue = $newValue->value;
        }

        // Standard comparison
        return $oldValue == $newValue;
    }
}
