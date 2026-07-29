<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Console\Command;

final class ReportOrderDomainStatusMigration extends Command
{
    protected $signature = 'orders:domain-status-report';

    protected $description = 'Report order statuses and payment records that need an explicit migration decision';

    public function handle(): int
    {
        $orders = Order::query()
            ->select(['id', 'uuid', 'status', 'lifecycle_status', 'disposition_status'])
            ->where(function ($query): void {
                $query
                    ->whereNull('lifecycle_status')
                    ->orWhereNull('disposition_status');
            })
            ->orderBy('id')
            ->get();

        $safeLegacyOrders = $orders->filter(fn(Order $order): bool => $this->hasSafeDomainMapping($order));
        $unresolvedOrders = $orders->reject(fn(Order $order): bool => $this->hasSafeDomainMapping($order));

        $this->line("Orders with safe mappings: {$safeLegacyOrders->count()}");
        $this->line("Unresolved orders: {$unresolvedOrders->count()}");

        foreach ($unresolvedOrders as $order) {
            $this->line(sprintf(
                'Order #%d (%s): status=%s, lifecycle_status=%s, disposition_status=%s',
                $order->id,
                $order->uuid,
                $order->status->value,
                $order->lifecycle_status === null ? 'null' : $order->lifecycle_status->value,
                $order->disposition_status === null ? 'null' : $order->disposition_status->value,
            ));
        }

        $missingPaymentActors = OrderPayment::query()
            ->whereNull('created_by')
            ->count();

        $this->line("Payments with unknown actor: {$missingPaymentActors}");

        if ($unresolvedOrders->isNotEmpty()) {
            $this->warn('No lifecycle or disposition value was inferred for unresolved orders.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function hasSafeDomainMapping(Order $order): bool
    {
        if ($order->lifecycle_status !== null || $order->disposition_status !== null) {
            return true;
        }

        return $order->status !== null
            && (OrderLifecycleStatus::tryFrom($order->status->value) !== null
                || OrderDispositionStatus::tryFrom($order->status->value) !== null);
    }
}
