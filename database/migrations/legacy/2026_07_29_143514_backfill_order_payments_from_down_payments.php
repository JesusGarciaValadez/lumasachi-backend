<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        DB::table('order_motor_info')
            ->where('down_payment', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($motorInfoRows): void {
                foreach ($motorInfoRows as $motorInfo) {
                    DB::table('order_payments')->insert([
                        'uuid' => Str::uuid7()->toString(),
                        'order_id' => $motorInfo->order_id,
                        'amount' => $motorInfo->down_payment,
                        // The motor-info creation time is the only timestamp retained by the legacy record.
                        'received_at' => $motorInfo->created_at,
                        // Legacy payment actors were not stored, so this remains unknown.
                        'created_by' => null,
                        'created_at' => $motorInfo->created_at,
                        'updated_at' => $motorInfo->updated_at ?? $motorInfo->created_at,
                    ]);
                }
            });
    }

    /**
     * The backfill is intentionally irreversible because it creates ledger records from legacy values.
     */
    public function down(): void
    {
    }
};
