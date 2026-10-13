<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_support')->default(false);
            $table->uuid('central_actor_uuid')->nullable()->unique();
        });

        Schema::table('order_histories', function (Blueprint $table): void {
            $table->uuid('central_actor_uuid')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_histories', function (Blueprint $table): void {
            $table->dropIndex('order_histories_central_actor_uuid_index');
            $table->dropColumn('central_actor_uuid');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_central_actor_uuid_unique');
            $table->dropColumn(['is_platform_support', 'central_actor_uuid']);
        });
    }
};
