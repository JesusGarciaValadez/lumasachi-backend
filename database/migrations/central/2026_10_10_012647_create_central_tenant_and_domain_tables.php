<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provisioning_status', 24)->default('pending')->index();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();

            $table->foreign('id')->references('uuid')->on('companies')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain', 255)->unique();
            $table->uuid('tenant_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        $this->restrictCentralDatabaseConnections();
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
    }

    /**
     * Restrict database CONNECT to the central app role and the configured provisioner when they use this database.
     */
    private function restrictCentralDatabaseConnections(): void
    {
        $centralConnectionName = config('tenancy.database.central_connection');

        if (!is_string($centralConnectionName) || $centralConnectionName === '') {
            throw new RuntimeException('The central PostgreSQL connection must be configured before migrating the registry.');
        }

        $centralConnection = DB::connection($centralConnectionName);
        $databaseName = $centralConnection->getDatabaseName();
        $databaseIdentifier = $this->quoteIdentifier($databaseName);
        $centralConfiguration = (new ConfigurationUrlParser)->parseConfiguration($centralConnection->getConfig());
        $centralUsername = $centralConfiguration['username'] ?? null;

        if (!is_string($centralUsername) || $centralUsername === '') {
            throw new RuntimeException('The central PostgreSQL connection requires a database username.');
        }

        $roleIdentifier = $this->quoteIdentifier($centralUsername);
        $centralConnection->statement('REVOKE CONNECT, TEMPORARY ON DATABASE ' . $databaseIdentifier . ' FROM PUBLIC');
        $centralConnection->statement('GRANT CONNECT ON DATABASE ' . $databaseIdentifier . ' TO ' . $roleIdentifier);

        $provisionerConnectionName = config('tenancy.database.provisioner_connection');
        $provisionerConfiguration = is_string($provisionerConnectionName)
            ? config("database.connections.{$provisionerConnectionName}")
            : null;

        if (!is_array($provisionerConfiguration) || ($provisionerConfiguration['driver'] ?? null) !== 'pgsql') {
            return;
        }

        $provisionerConfiguration = (new ConfigurationUrlParser)->parseConfiguration($provisionerConfiguration);

        if (($provisionerConfiguration['database'] ?? null) !== $databaseName) {
            return;
        }

        $provisionerUsername = $provisionerConfiguration['username'] ?? null;

        if (is_string($provisionerUsername) && $provisionerUsername !== '') {
            $provisionerRoleIdentifier = $this->quoteIdentifier($provisionerUsername);
            $centralConnection->statement('GRANT CONNECT ON DATABASE ' . $databaseIdentifier . ' TO ' . $provisionerRoleIdentifier);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
};
