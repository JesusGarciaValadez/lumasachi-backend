<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_services', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('service_key');
            $table->string('measurement')->nullable();

            $table->boolean('is_budgeted')->default(false);
            $table->boolean('is_authorized')->default(false);
            $table->boolean('is_completed')->default(false);

            $table->text('notes')->nullable();

            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('net_price', 10, 2)->nullable();

            $table->timestamps();

            $table->index(['order_item_id', 'is_budgeted', 'is_authorized', 'is_completed']);
            $table->index('service_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_services');
    }
};
