<?php

namespace App\Observers;

use App\Models\OrderHistory;
use App\Models\OrderService;
use App\Notifications\OrderAuditNotification;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class OrderServiceObserver
{
    public function updated(OrderService $service): void
    {
        $changed = false;

        if ($service->wasChanged('is_budgeted')) {
            $this->createHistory($service, OrderHistory::FIELD_SERVICE_BUDGETED, (bool) $service->getOriginal('is_budgeted'), (bool) $service->is_budgeted);
            $changed = true;
        }

        if ($service->wasChanged('is_authorized')) {
            $this->createHistory($service, OrderHistory::FIELD_SERVICE_AUTHORIZED, (bool) $service->getOriginal('is_authorized'), (bool) $service->is_authorized);
            $changed = true;
        }

        if ($service->wasChanged('is_completed')) {
            $this->createHistory($service, OrderHistory::FIELD_SERVICE_COMPLETED, (bool) $service->getOriginal('is_completed'), (bool) $service->is_completed);
            $changed = true;

            // Optional: notify admins for audit when a service is completed
            $this->notifyAdmins(new OrderAuditNotification($service->orderItem->order, 'service_completed'));
        }
    }

    private function createHistory(OrderService $service, string $field, bool $old, bool $new): void
    {
        $order = $service->orderItem->order;

        OrderHistory::create([
            'uuid' => Str::uuid7()->toString(),
            'order_id' => $service->orderItem->order_id,
            'field_changed' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'created_by' => auth()?->id() ?? $order?->updated_by ?? $order?->created_by,
        ]);
    }

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
