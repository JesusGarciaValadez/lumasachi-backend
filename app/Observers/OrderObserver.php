<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Str;

class OrderObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Notify the customer of the order
        if ($order->customer) {
            $order->customer->notify(new OrderCreatedNotification($order));
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        $trackedFields = [
            'uuid',
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
                    'uuid' => Str::uuid()->toString(),
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
