<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER SEQUENCE public.orders_id_seq OWNED BY public.orders.id');

        DB::statement(<<<'SQL'
            SELECT setval(
                'public.orders_id_seq'::regclass,
                COALESCE(MAX(id), 1),
                MAX(id) IS NOT NULL
            )
            FROM public.orders
            SQL
        );
    }

    public function down(): void
    {
        // The imported sequence ownership and value cannot be safely restored.
    }
};
