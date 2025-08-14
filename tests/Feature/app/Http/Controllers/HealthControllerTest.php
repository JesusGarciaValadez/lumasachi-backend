<?php

namespace Tests\Feature\app\Http\Controllers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class HealthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the simple health check endpoint returns correct response.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_up_endpoint_returns_operational_status(): void
    {
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
                'laravel_version'
            ])
            ->assertJson([
                'status' => 'up',
                'message' => 'API is operational'
            ]);

        // Verify timestamp is valid ISO 8601
        $data = $response->json();
        $this->assertNotNull(\DateTime::createFromFormat(\DateTime::ISO8601, $data['timestamp']));
    }

    /**
     * Test comprehensive health check returns healthy status when all checks pass.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_health_endpoint_returns_healthy_status_when_all_checks_pass(): void
    {
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
                        'connection'
                    ],
                    'cache' => [
                        'healthy',
                        'message',
                        'response_time_ms',
                        'driver'
                    ],
                    'storage' => [
                        'healthy',
                        'message',
                        'disks'
                    ],
                    'memory' => [
                        'healthy',
                        'message',
                        'usage_mb',
                        'limit_mb',
                        'usage_percentage'
                    ],
                    'disk' => [
                        'healthy',
                        'message',
                        'free_gb',
                        'total_gb',
                        'used_percentage'
                    ]
                ]
            ]);

        // Verify overall status is healthy
        $data = $response->json();
        $this->assertEquals('healthy', $data['status']);
        $this->assertIsNumeric($data['execution_time_ms']);
    }

    /**
     * Test health check database component works correctly.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_health_check_database_component(): void
    {
        // Ensure database is accessible
        DB::select('SELECT 1');

        // Act: Call the health endpoint
        $response = $this->getJson('/api/v1/health');

        // Assert: Check database component
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertTrue($data['checks']['database']['healthy']);
        $this->assertEquals('Database is responsive', $data['checks']['database']['message']);
        $this->assertIsNumeric($data['checks']['database']['response_time_ms']);
    }

    /**
     * Test health check cache component works correctly.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_health_check_cache_component(): void
    {
        // Act: Call the health endpoint
        $response = $this->getJson('/api/v1/health');

        // Assert: Check cache component
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('cache', $data['checks']);
        $this->assertTrue($data['checks']['cache']['healthy']);
        $this->assertEquals('Cache is operational', $data['checks']['cache']['message']);
        $this->assertIsNumeric($data['checks']['cache']['response_time_ms']);
    }

    /**
     * Test health check storage component works correctly.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_health_check_storage_component(): void
    {
        // Ensure storage is writable
        Storage::fake('local');
        Storage::fake('public');

        // Act: Call the health endpoint
        $response = $this->getJson('/api/v1/health');

        // Assert: Check storage component
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('storage', $data['checks']);
        $this->assertArrayHasKey('disks', $data['checks']['storage']);
    }

    /**
     * Test health check memory component works correctly.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_health_check_memory_component(): void
    {
        // Act: Call the health endpoint
        $response = $this->getJson('/api/v1/health');

        // Assert: Check memory component
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('memory', $data['checks']);
        $this->assertIsNumeric($data['checks']['memory']['usage_mb']);
        $this->assertIsNumeric($data['checks']['memory']['limit_mb']);
        $this->assertIsNumeric($data['checks']['memory']['usage_percentage']);
        $this->assertLessThan(100, $data['checks']['memory']['usage_percentage']);
    }

    /**
     * Test health check disk space component works correctly.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_health_check_disk_space_component(): void
    {
        // Act: Call the health endpoint
        $response = $this->getJson('/api/v1/health');

        // Assert: Check disk space component
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('disk', $data['checks']);
        $this->assertIsNumeric($data['checks']['disk']['free_gb']);
        $this->assertIsNumeric($data['checks']['disk']['total_gb']);
        $this->assertIsNumeric($data['checks']['disk']['used_percentage']);
        $this->assertLessThan(100, $data['checks']['disk']['used_percentage']);
    }

    /**
     * Test health check returns unhealthy status when a component fails.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_health_check_returns_unhealthy_when_component_fails(): void
    {
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

        $this->assertEquals('unhealthy', $data['status']);
        $this->assertFalse($data['checks']['database']['healthy']);
        $this->assertEquals('Database connection failed', $data['checks']['database']['message']);
    }

    /**
     * Test queue check is included when queue is not sync.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_queue_check_included_when_queue_not_sync(): void
    {
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

        $this->assertArrayHasKey('queue', $data['checks']);
        $this->assertTrue($data['checks']['queue']['healthy']);
        $this->assertEquals('Queue is operational', $data['checks']['queue']['message']);
        $this->assertArrayHasKey('pending_jobs', $data['checks']['queue']);
        $this->assertArrayHasKey('failed_jobs', $data['checks']['queue']);
    }

    /**
     * Test queue check is not included when queue is sync.
     *
     * @return void
     */
    #[Test]
    public function it_checks_if_queue_check_not_included_when_queue_is_sync(): void
    {
        // Arrange: Ensure queue driver is sync
        config(['queue.default' => 'sync']);

        // Act: Call the health endpoint
        $response = $this->getJson('/api/v1/health');

        // Assert: Check queue component is not included
        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayNotHasKey('queue', $data['checks']);
    }
}
