<?php

namespace Modules\Lumasachi\app\Observers;

use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Notifications\OrderCreatedNotification;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Notify the user who created the order
        if ($order->createdBy) {
            $order->createdBy->notify(new OrderCreatedNotification($order));
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $trackedFields = [
            'status',
            'priority',
            'assigned_to',
            'estimated_completion',
            'title',
            'description',
            'notes',
            'category_id'
        ];

        foreach ($trackedFields as $field) {
            if ($order->isDirty($field)) {
                OrderHistory::create([
                    'order_id' => $order->id,
                    'field_changed' => $field,
                    'old_value' => $order->getOriginal($field),
                    'new_value' => $order->getAttribute($field),
                    'created_by' => auth()->id() ?? $order->updated_by,
                ]);
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
