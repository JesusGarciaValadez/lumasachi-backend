<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Console\Command;

final class ReportOrderDomainStatusMigration extends Command
{
    protected $signature = 'orders:domain-status-report';

    protected $description = 'Report orders without canonical lifecycle or disposition status values';

    public function handle(): int
    {
        $orders = Order::query()
            ->select(['id', 'uuid', 'lifecycle_status', 'disposition_status'])
            ->where(function ($query): void {
                $query
                    ->whereNull('lifecycle_status')
                    ->orWhereNotNull('disposition_status')
                    ->whereNotIn('disposition_status', ['Returned', 'Cancelled']);
            })
            ->orderBy('id')
            ->get();

        $this->line("Orders requiring canonical status review: {$orders->count()}");

        foreach ($orders as $order) {
            $this->line(sprintf(
                'Order #%d (%s): lifecycle_status=%s, disposition_status=%s',
                $order->id,
                $order->uuid,
                $order->lifecycle_status === null ? 'null' : $order->lifecycle_status->value,
                $order->disposition_status === null ? 'null' : $order->disposition_status->value,
            ));
        }

        $missingPaymentActors = OrderPayment::query()
            ->whereNull('created_by')
            ->count();

        $this->line("Payments with unknown actor: {$missingPaymentActors}");

        if ($orders->isNotEmpty()) {
            $this->warn('Some orders do not use canonical lifecycle/disposition values.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
