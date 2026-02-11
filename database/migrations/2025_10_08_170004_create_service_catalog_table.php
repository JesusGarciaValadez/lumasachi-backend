<?php

declare(strict_types=1);

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
        Schema::create('service_catalog', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('service_key')->unique();
            $table->string('service_name_key');
            $table->string('item_type');

            $table->decimal('base_price', 10, 2);
            $table->decimal('tax_percentage', 5, 2)->default(16.00);

            $table->boolean('requires_measurement')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);

            $table->timestamps();

            $table->index(['item_type', 'is_active']);
            $table->index('display_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_catalog');
    }
};
