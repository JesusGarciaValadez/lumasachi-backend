<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('checks if up endpoint returns operational status', function () {
    // Act: Call the /up endpoint
    $response = $this->getJson('/api/v1/up');

    // Assert: Check response status and structure
    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'message',
            'timestamp',
            'environment',
            'version',
            'php_version',
            'laravel_version',
        ])
        ->assertJson([
            'status' => 'up',
            'message' => 'API is operational',
        ]);

    // Verify timestamp is valid ISO 8601
    $data = $response->json();
    expect(DateTimeImmutable::createFromFormat(DateTime::ISO8601, $data['timestamp']))->not->toBeNull();
});
it('checks if health endpoint returns healthy status when all checks pass', function () {
    // Act: Call the /health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check response status and structure
    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
            'version',
            'execution_time_ms',
            'checks' => [
                'database' => [
                    'healthy',
                    'message',
                    'response_time_ms',
                    'connection',
                ],
                'cache' => [
                    'healthy',
                    'message',
                    'response_time_ms',
                    'driver',
                ],
                'storage' => [
                    'healthy',
                    'message',
                    'disks',
                ],
                'memory' => [
                    'healthy',
                    'message',
                    'usage_mb',
                    'limit_mb',
                    'usage_percentage',
                ],
                'disk' => [
                    'healthy',
                    'message',
                    'free_gb',
                    'total_gb',
                    'used_percentage',
                ],
            ],
        ]);

    // Verify overall status is healthy
    $data = $response->json();
    expect($data['status'])->toEqual('healthy');
    expect($data['execution_time_ms'])->toBeNumeric();
});
it('checks if health check database component', function () {
    // Ensure database is accessible
    DB::select('SELECT 1');

    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check database component
    $response->assertOk();
    $data = $response->json();

    expect($data['checks'])->toHaveKey('database');
    expect($data['checks']['database']['healthy'])->toBeTrue();
    expect($data['checks']['database']['message'])->toEqual('Database is responsive');
    expect($data['checks']['database']['response_time_ms'])->toBeNumeric();
});
it('checks if health check cache component', function () {
    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check cache component
    $response->assertStatus(200);
    $data = $response->json();

    expect($data['checks'])->toHaveKey('cache');
    expect($data['checks']['cache']['healthy'])->toBeTrue();
    expect($data['checks']['cache']['message'])->toEqual('Cache is operational');
    expect($data['checks']['cache']['response_time_ms'])->toBeNumeric();
});
it('checks if health check storage component', function () {
    // Ensure storage is writable
    Storage::fake('local');
    Storage::fake('public');

    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check storage component
    $response->assertStatus(200);
    $data = $response->json();

    expect($data['checks'])->toHaveKey('storage');
    expect($data['checks']['storage']['healthy'])->toBeTrue();
    expect($data['checks']['storage'])->toHaveKey('disks');
});
it('checks if health check memory component', function () {
    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check memory component
    $response->assertStatus(200);
    $data = $response->json();

    expect($data['checks'])->toHaveKey('memory');
    expect($data['checks']['memory']['usage_mb'])->toBeNumeric();
    expect($data['checks']['memory']['limit_mb'])->toBeNumeric();
    expect($data['checks']['memory']['usage_percentage'])->toBeNumeric();
    expect($data['checks']['memory']['usage_percentage'])->toBeLessThan(100);
});
it('checks if health check disk space component', function () {
    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check disk space component
    $response->assertStatus(200);
    $data = $response->json();

    expect($data['checks'])->toHaveKey('disk');
    expect($data['checks']['disk']['free_gb'])->toBeNumeric();
    expect($data['checks']['disk']['total_gb'])->toBeNumeric();
    expect($data['checks']['disk']['used_percentage'])->toBeNumeric();
    expect($data['checks']['disk']['used_percentage'])->toBeLessThan(100);
});
it('checks if health check returns unhealthy when component fails', function () {
    // Get the current database connection name
    $currentConnection = config('database.default');

    // Store the original database config
    $originalConfig = config("database.connections.{$currentConnection}");

    // Mock a database failure by setting invalid connection parameters
    if ($currentConnection === 'pgsql') {
        // For PostgreSQL, set an invalid host
        config(["database.connections.{$currentConnection}.host" => 'invalid.host.that.does.not.exist']);
        config(["database.connections.{$currentConnection}.port" => '99999']);
    } else {
        // For SQLite or other databases
        config(["database.connections.{$currentConnection}.database" => '/invalid/path/database.sqlite']);
    }

    // Clear the database instance to force reconnection with new config
    DB::purge($currentConnection);

    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Restore the original database config
    config(["database.connections.{$currentConnection}" => $originalConfig]);
    DB::purge($currentConnection);

    // Assert: Check response indicates unhealthy status
    $response->assertStatus(503);
    $data = $response->json();

    expect($data['status'])->toEqual('unhealthy');
    expect($data['checks']['database']['healthy'])->toBeFalse();
    expect($data['checks']['database']['message'])->toEqual('Database connection failed');
});
it('checks if queue check included when queue not sync', function () {
    // Arrange: Set queue driver to database
    config(['queue.default' => 'database']);

    // Create queue tables if they don't exist
    if (!DB::getSchemaBuilder()->hasTable('jobs')) {
        DB::getSchemaBuilder()->create('jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    if (!DB::getSchemaBuilder()->hasTable('failed_jobs')) {
        DB::getSchemaBuilder()->create('failed_jobs', function ($table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check queue component is included
    $response->assertStatus(200);
    $data = $response->json();

    expect($data['checks'])->toHaveKey('queue');
    expect($data['checks']['queue']['healthy'])->toBeTrue();
    expect($data['checks']['queue']['message'])->toEqual('Queue is operational');
    expect($data['checks']['queue'])->toHaveKey('pending_jobs');
    expect($data['checks']['queue'])->toHaveKey('failed_jobs');
});
it('checks if queue check not included when queue is sync', function () {
    // Arrange: Ensure queue driver is sync
    config(['queue.default' => 'sync']);

    // Act: Call the health endpoint
    $response = $this->getJson('/api/v1/health');

    // Assert: Check queue component is not included
    $response->assertStatus(200);
    $data = $response->json();

    $this->assertArrayNotHasKey('queue', $data['checks']);
});
