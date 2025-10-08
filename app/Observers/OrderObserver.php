<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderReviewedNotification;
use App\Notifications\OrderReadyForDeliveryNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderAuditNotification;
use Illuminate\Support\Str;
use Illuminate\Notifications\Notification;
use App\Traits\CachesOrders;

class OrderObserver
{
    use CachesOrders;
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

        // Audit: notify admins and super admins
        $this->notifyAdmins(new OrderAuditNotification($order, 'created'));

        // Invalidate orders cache namespace
        self::bumpVersion();
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
            // Only record history for fields that actually changed in this update
            if (! $order->wasChanged($field)) {
                continue;
            }

            $old = $original[$field] ?? null;
            $new = $order->getAttribute($field);

            // Normalize Carbon instances to strings for comparison
            if ($old instanceof \Carbon\CarbonInterface) {
                $old = $old->toISOString();
            }
            if ($new instanceof \Carbon\CarbonInterface) {
                $new = $new->toISOString();
            }
            // Normalize enums to values
            if ($new instanceof \BackedEnum) {
                $new = $new->value;
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

        // After logging changes, handle status transition side-effects
        $oldStatus = $original['status'] ?? null; // string or null
        $newStatus = $order->status?->value; // string

        if ($oldStatus !== $newStatus && $newStatus) {
            // Reviewed: notify customer and admins, then auto-transition to Awaiting Customer Approval
            if ($newStatus === OrderStatus::REVIEWED->value) {
                if ($order->customer) {
                    $order->customer->notify(new OrderReviewedNotification($order));
                }
                $this->notifyAdmins(new OrderAuditNotification($order, 'reviewed'));

                // Auto-transition to Awaiting Customer Approval
                $order->update(['status' => OrderStatus::AWAITING_CUSTOMER_APPROVAL->value]);
            }

            // Ready for delivery: notify customer
            if ($newStatus === OrderStatus::READY_FOR_DELIVERY->value) {
                if ($order->customer) {
                    $order->customer->notify(new OrderReadyForDeliveryNotification($order));
                }
            }

            // Delivered: notify customer and admins
            if ($newStatus === OrderStatus::DELIVERED->value) {
                if ($order->customer) {
                    $order->customer->notify(new OrderDeliveredNotification($order));
                }
                $this->notifyAdmins(new OrderAuditNotification($order, 'delivered'));
            }
        }

        // Invalidate orders cache namespace
        self::bumpVersion();
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // Invalidate orders cache namespace
        self::bumpVersion();
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        // Invalidate orders cache namespace
        self::bumpVersion();
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        // Invalidate orders cache namespace
        self::bumpVersion();
    }
    /**
     * Notify admin and super admin users.
     */
    private function notifyAdmins(Notification $notification): void
    {
        $admins = User::query()
            ->whereIn('role', [UserRole::ADMINISTRATOR->value, UserRole::SUPER_ADMINISTRATOR->value])
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
