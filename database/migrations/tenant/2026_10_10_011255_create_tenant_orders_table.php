<?php

declare(strict_types=1);

use App\Enums\OrderPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('customer_id')
                ->index()
                ->constrained('users')
                ->cascadeOnUpdate();
            $table->foreignId('assigned_to')
                ->constrained('users')
                ->cascadeOnUpdate();
            $table->string('title');
            $table->text('description');
            $table->string('lifecycle_status');
            $table->string('disposition_status')->nullable();
            $table->enum('priority', OrderPriority::getPriorities());
            $table->timestamp('estimated_completion')->nullable();
            $table->timestamp('actual_completion')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnUpdate();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate();

            $table->index(['lifecycle_status', 'priority']);
            $table->index(['disposition_status', 'priority']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
