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
        Schema::create('order_item_components', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('component_name');
            $table->boolean('is_received')->default(false);
            $table->timestamps();

            $table->index('order_item_id');
            $table->unique(['order_item_id', 'component_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_components');
    }
};
