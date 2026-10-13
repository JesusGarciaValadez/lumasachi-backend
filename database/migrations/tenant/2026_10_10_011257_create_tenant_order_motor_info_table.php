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
        Schema::create('order_motor_info', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('brand')->nullable();
            $table->string('liters')->nullable();
            $table->string('year')->nullable();
            $table->string('model')->nullable();
            $table->string('cylinder_count')->nullable();
            $table->string('center_torque')->nullable();
            $table->string('rod_torque')->nullable();
            $table->string('first_gap')->nullable();
            $table->string('second_gap')->nullable();
            $table->string('third_gap')->nullable();
            $table->string('center_clearance')->nullable();
            $table->string('rod_clearance')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_motor_info');
    }
};
