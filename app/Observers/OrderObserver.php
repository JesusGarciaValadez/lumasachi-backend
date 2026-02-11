<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Notifications\OrderAuditNotification;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderPaidNotification;
use App\Notifications\OrderReadyForDeliveryNotification;
use App\Notifications\OrderReadyForWorkNotification;
use App\Notifications\OrderReceivedNotification;
use App\Notifications\OrderReviewedNotification;
use App\Traits\CachesOrders;
use App\Traits\NotifiesAdmins;
use BackedEnum;
use Illuminate\Support\Str;

final class OrderObserver
{
    use CachesOrders;
    use NotifiesAdmins;

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
            if ($new instanceof BackedEnum) {
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
        $oldStatus = $original['status'] ?? null;
        $newStatus = $order->status?->value;

        if ($oldStatus !== $newStatus && $newStatus) {
            $this->handleStatusTransition($order, $newStatus);
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
     * Handle status transition side-effects (notifications).
     */
    private function handleStatusTransition(Order $order, string $newStatus): void
    {
        /** @var array<string, array{notification: class-string, audit: string|null}> */
        $transitions = [
            OrderStatus::Received->value => [
                'notification' => OrderReceivedNotification::class,
                'audit' => 'received',
            ],
            OrderStatus::Reviewed->value => [
                'notification' => OrderReviewedNotification::class,
                'audit' => 'reviewed',
            ],
            OrderStatus::ReadyForWork->value => [
                'notification' => OrderReadyForWorkNotification::class,
                'audit' => 'ready_for_work',
            ],
            OrderStatus::ReadyForDelivery->value => [
                'notification' => OrderReadyForDeliveryNotification::class,
                'audit' => null,
            ],
            OrderStatus::Delivered->value => [
                'notification' => OrderDeliveredNotification::class,
                'audit' => 'delivered',
            ],
            OrderStatus::Paid->value => [
                'notification' => OrderPaidNotification::class,
                'audit' => 'paid',
            ],
        ];

        $transition = $transitions[$newStatus] ?? null;

        if ($transition) {
            $customer = $order->customer;

            if ($customer) {
                $customer->notify(new $transition['notification']($order));
            }

            if ($transition['audit']) {
                $this->notifyAdmins(new OrderAuditNotification($order, $transition['audit']));
            }
        }

        // Auto-transition: Reviewed → Awaiting Customer Approval
        if ($newStatus === OrderStatus::Reviewed->value) {
            $order->updateQuietly(['status' => OrderStatus::AwaitingCustomerApproval->value]);
        }
    }
}
