<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_onboardings', function (Blueprint $table): void {
            $table->uuid('tenant_id')->primary();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('tenant_domain', 255)->unique();
            $table->string('administrator_first_name');
            $table->string('administrator_last_name');
            $table->string('administrator_email');
            $table->uuid('administrator_uuid')->unique();
            $table->uuid('created_by_central_actor_uuid')->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->uuid('provisioning_lock_token')->nullable()->unique();
            $table->timestampTz('provisioning_locked_at')->nullable();
            $table->timestampTz('database_creation_started_at')->nullable();
            $table->timestampTz('database_created_at')->nullable();
            $table->timestampTz('migrations_completed_at')->nullable();
            $table->timestampTz('defaults_seeded_at')->nullable();
            $table->timestampTz('company_settings_created_at')->nullable();
            $table->timestampTz('administrator_created_at')->nullable();
            $table->timestampTz('invitation_queued_at')->nullable();
            $table->string('last_failed_stage', 40)->nullable();
            $table->string('last_failure_type', 255)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_onboardings');
    }
};
