<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderService;
use App\Notifications\OrderAuditNotification;
use App\Traits\NotifiesAdmins;
use Illuminate\Support\Str;

final class OrderServiceObserver
{
    use NotifiesAdmins;

    public function updated(OrderService $service): void
    {
        $order = $service->orderItem->order;
        $changed = false;

        if ($service->wasChanged('is_budgeted')) {
            $this->createHistory($order, OrderHistory::FIELD_SERVICE_BUDGETED, (bool) $service->getOriginal('is_budgeted'), (bool) $service->is_budgeted);
            $changed = true;
        }

        if ($service->wasChanged('is_authorized')) {
            $this->createHistory($order, OrderHistory::FIELD_SERVICE_AUTHORIZED, (bool) $service->getOriginal('is_authorized'), (bool) $service->is_authorized);
            $changed = true;
        }

        if ($service->wasChanged('is_completed')) {
            $this->createHistory($order, OrderHistory::FIELD_SERVICE_COMPLETED, (bool) $service->getOriginal('is_completed'), (bool) $service->is_completed);
            $changed = true;

            $this->notifyAdmins(new OrderAuditNotification($order, 'service_completed'));
        }

        if ($changed) {
            $order->recalculateTotals();
        }
    }

    private function createHistory(Order $order, string $field, bool $old, bool $new): void
    {
        OrderHistory::create([
            'uuid' => Str::uuid7()->toString(),
            'order_id' => $order->id,
            'field_changed' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'created_by' => auth()?->id() ?? $order->updated_by ?? $order->created_by,
        ]);
    }
}
