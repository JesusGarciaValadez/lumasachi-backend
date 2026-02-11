<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize inconsistent capitalization in orders.status and order_histories values.
     */
    public function up(): void
    {
        $mapping = [
            'Ready for delivery' => 'Ready for Delivery',
            'Not paid' => 'Not Paid',
            'On hold' => 'On Hold',
        ];

        DB::transaction(function () use ($mapping): void {
            foreach ($mapping as $old => $new) {
                DB::table('orders')->where('status', $old)->update(['status' => $new]);
                DB::table('order_histories')->where('old_value', $old)->update(['old_value' => $new]);
                DB::table('order_histories')->where('new_value', $old)->update(['new_value' => $new]);
            }
        });
    }

    /**
     * Reverse the normalization.
     */
    public function down(): void
    {
        $mapping = [
            'Ready for Delivery' => 'Ready for delivery',
            'Not Paid' => 'Not paid',
            'On Hold' => 'On hold',
        ];

        DB::transaction(function () use ($mapping): void {
            foreach ($mapping as $old => $new) {
                DB::table('orders')->where('status', $old)->update(['status' => $new]);
                DB::table('order_histories')->where('old_value', $old)->update(['old_value' => $new]);
                DB::table('order_histories')->where('new_value', $old)->update(['new_value' => $new]);
            }
        });
    }
};
