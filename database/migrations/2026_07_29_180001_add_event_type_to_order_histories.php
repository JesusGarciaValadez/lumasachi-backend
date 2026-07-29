<?php

declare(strict_types=1);

use App\Enums\OrderHistoryEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_histories', function (Blueprint $table): void {
            $table->string('event_type')
                ->default(OrderHistoryEventType::Attribute->value)
                ->after('field_changed');

            $table->index(['order_id', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_histories', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'event_type', 'created_at']);
            $table->dropColumn('event_type');
        });
    }
};
