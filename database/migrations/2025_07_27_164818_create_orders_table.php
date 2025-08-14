<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('customer_id')
                ->index()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->foreignId('assigned_to')
                ->constrained('users')
                ->cascadeOnUpdate();
            $table->string('title');
            $table->text('description');
            $table->enum('status', OrderStatus::getStatuses());
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

            $table->index(['status', 'priority']);
            $table->index(['created_by', 'status']);
            $table->index(['assigned_to', 'status']);

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
