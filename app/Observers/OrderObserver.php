<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Support\Str;

class OrderObserver
{
    /**
     * Store original values during updating to compute history afterwards.
     */
    protected static array $originals = [];

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
     * Capture original values before updating.
     */
    public function updating(Order $order): void
    {
        self::$originals[$order->getKey()] = $order->getOriginal();
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
        ];

        $original = self::$originals[$order->getKey()] ?? [];
        unset(self::$originals[$order->getKey()]);

        foreach ($trackedFields as $field) {
            $old = $original[$field] ?? null;
            $new = $order->getAttribute($field);

            // Normalize Carbon instances to strings for comparison
            if ($old instanceof \Carbon\CarbonInterface) {
                $old = $old->toISOString();
            }
            if ($new instanceof \Carbon\CarbonInterface) {
                $new = $new->toISOString();
            }

            if ($old !== $new) {
                OrderHistory::create([
                    'uuid' => Str::uuid7()->toString(),
                    'order_id' => $order->id,
                    'field_changed' => $field,
                    'old_value' => $old,
                    'new_value' => $new,
                    'created_by' => auth()?->id() ?? $order->updated_by,
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
