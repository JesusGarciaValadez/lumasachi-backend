<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('lifecycle_status')->nullable()->after('status');
            $table->string('disposition_status')->nullable()->after('lifecycle_status');

            $table->index(['lifecycle_status', 'priority']);
            $table->index(['disposition_status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['lifecycle_status', 'priority']);
            $table->dropIndex(['disposition_status', 'priority']);
            $table->dropColumn(['lifecycle_status', 'disposition_status']);
        });
    }
};
