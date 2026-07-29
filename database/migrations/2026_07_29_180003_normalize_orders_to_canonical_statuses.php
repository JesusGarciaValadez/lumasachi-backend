<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Normalize the non-production mixed-status data before removing its column.
     */
    public function up(): void
    {
        $lifecycleStatuses = [
            'Received',
            'Awaiting Review',
            'Reviewed',
            'Awaiting Customer Approval',
            'Ready for Work',
            'Ready for Delivery',
            'Delivered',
        ];

        $legacyLifecycleMap = [
            'In Progress' => 'Ready for Work',
            'Completed' => 'Ready for Delivery',
            'Open' => 'Received',
            'On Hold' => 'Received',
        ];

        $normalizeLifecycle = static function (?string $value) use ($lifecycleStatuses, $legacyLifecycleMap): ?string {
            if ($value === null) {
                return null;
            }

            return in_array($value, $lifecycleStatuses, true)
                ? $value
                : ($legacyLifecycleMap[$value] ?? null);
        };

        $normalizePayment = static function (?string $value): ?string {
            return match ($value) {
                'Paid' => 'Paid',
                'Not Paid' => 'Unpaid',
                default => null,
            };
        };

        $normalizeDisposition = static function (?string $value): ?string {
            return in_array($value, ['Returned', 'Cancelled'], true) ? $value : null;
        };

        DB::transaction(function () use (
            $normalizeLifecycle,
            $normalizePayment,
            $normalizeDisposition,
        ): void {
            $orders = DB::table('orders')->orderBy('id')->get();

            foreach ($orders as $order) {
                $histories = DB::table('order_histories')
                    ->where('order_id', $order->id)
                    ->where('field_changed', 'status')
                    ->orderBy('id')
                    ->get();

                $historyLifecycle = null;

                foreach ($histories as $history) {
                    $normalizedNew = $normalizeLifecycle($history->new_value);

                    if ($normalizedNew !== null && $historyLifecycle !== 'Delivered') {
                        $historyLifecycle = $normalizedNew;
                    }
                }

                $lifecycleStatus = $normalizeLifecycle($order->lifecycle_status)
                    ?? $normalizeLifecycle($order->status)
                    ?? $historyLifecycle
                    ?? 'Received';
                $dispositionStatus = $order->disposition_status
                    ?? $normalizeDisposition($order->status);

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'lifecycle_status' => $lifecycleStatus,
                        'disposition_status' => $dispositionStatus,
                    ]);

                foreach ($histories as $history) {
                    $newPayment = $normalizePayment($history->new_value);
                    $newDisposition = $normalizeDisposition($history->new_value);

                    if ($newPayment !== null) {
                        DB::table('order_histories')
                            ->where('id', $history->id)
                            ->update([
                                'field_changed' => 'payment_status',
                                'event_type' => 'payment',
                                'old_value' => $normalizePayment($history->old_value),
                                'new_value' => $newPayment,
                            ]);

                        continue;
                    }

                    if ($newDisposition !== null) {
                        DB::table('order_histories')
                            ->where('id', $history->id)
                            ->update([
                                'field_changed' => 'disposition_status',
                                'event_type' => 'disposition',
                                'old_value' => $normalizeDisposition($history->old_value),
                                'new_value' => $newDisposition,
                            ]);

                        continue;
                    }

                    DB::table('order_histories')
                        ->where('id', $history->id)
                        ->update([
                            'field_changed' => 'lifecycle_status',
                            'event_type' => 'lifecycle',
                            'old_value' => $normalizeLifecycle($history->old_value),
                            'new_value' => $normalizeLifecycle($history->new_value),
                        ]);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('lifecycle_status')->nullable(false)->change();
            $table->dropIndex(['status', 'priority']);
            $table->dropIndex(['created_by', 'status']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropColumn('status');
        });
    }

    /**
     * This migration is intentionally irreversible because it removes the
     * non-production mixed-status source of truth.
     */
    public function down(): void
    {
    }
};
