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
        Schema::create('central_tenant_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->uuid('tenant_id')->index();
            $table->uuid('central_actor_uuid')->index();
            $table->string('central_origin', 255);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('entered_at')->nullable();
            $table->timestampTz('exited_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('central_tenant_access_grants');
    }
};
