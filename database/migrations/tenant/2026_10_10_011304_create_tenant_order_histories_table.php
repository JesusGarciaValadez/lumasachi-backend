<?php

declare(strict_types=1);

use App\Enums\OrderHistoryEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_histories', function (Blueprint $table): void {
            $table->id();
            $table->uuid();
            $table->foreignId('order_id')
                ->index()
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('field_changed');
            $table->string('event_type')->default(OrderHistoryEventType::Attribute->value);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('created_by')
                ->index()
                ->constrained('users')
                ->cascadeOnUpdate();
            $table->timestamps();
            $table->index(['order_id', 'field_changed']);
            $table->index(['order_id', 'created_at']);
            $table->index(['order_id', 'event_type', 'created_at']);
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
