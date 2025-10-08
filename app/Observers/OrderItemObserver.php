<?php

namespace App\Observers;

use App\Models\OrderHistory;
use App\Models\OrderItem;
use App\Notifications\OrderAuditNotification;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class OrderItemObserver
{
    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $item): void
    {
        // Track item received status
        if ($item->wasChanged('is_received')) {
            $order = $item->order; // may be null if relation not loaded

            OrderHistory::create([
                'uuid' => Str::uuid7()->toString(),
                'order_id' => $item->order_id,
                'field_changed' => OrderHistory::FIELD_ITEM_RECEIVED,
                'old_value' => (bool) $item->getOriginal('is_received'),
                'new_value' => (bool) $item->is_received,
                'created_by' => auth()?->id() ?? $order?->updated_by ?? $order?->created_by,
            ]);
        }
    }
}
