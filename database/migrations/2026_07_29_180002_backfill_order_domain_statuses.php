<?php

declare(strict_types=1);

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (OrderLifecycleStatus::cases() as $status) {
            DB::table('orders')
                ->whereNull('lifecycle_status')
                ->where('status', $status->value)
                ->update(['lifecycle_status' => $status->value]);
        }

        foreach (OrderDispositionStatus::cases() as $status) {
            DB::table('orders')
                ->whereNull('disposition_status')
                ->where('status', $status->value)
                ->update(['disposition_status' => $status->value]);
        }
    }

    public function down(): void
    {
        DB::table('orders')
            ->whereColumn('lifecycle_status', 'status')
            ->update(['lifecycle_status' => null]);

        DB::table('orders')
            ->whereColumn('disposition_status', 'status')
            ->update(['disposition_status' => null]);
    }
};
