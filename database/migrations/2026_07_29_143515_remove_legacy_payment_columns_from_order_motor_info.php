<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_motor_info', function (Blueprint $table): void {
            $table->dropIndex(['is_fully_paid']);
            $table->dropColumn(['down_payment', 'total_cost', 'is_fully_paid']);
        });
    }

    public function down(): void
    {
        Schema::table('order_motor_info', function (Blueprint $table): void {
            $table->decimal('down_payment', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->boolean('is_fully_paid')->default(false);
            $table->index('is_fully_paid');
        });
    }
};
