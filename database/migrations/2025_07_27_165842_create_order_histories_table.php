<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Order;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id()->unsigned()->primary();
            $table->uuid();
            $table->foreignId('order_id')
                ->index()
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('field_changed'); // e.g., 'status', 'priority', 'assigned_to', etc.
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('comment')->nullable(); // User comment about the change
            $table->foreignId('created_by')
                ->index()
                ->constrained('users')
                ->cascadeOnUpdate();
            $table->timestamps();

            // Add index for common queries
            $table->index(['order_id', 'field_changed']);
            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};
