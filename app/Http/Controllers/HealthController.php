<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

final class HealthController extends Controller
{
    /**
     * Simple health check endpoint.
     * Returns basic API status.
     *
     * @return JsonResponse
     */
    public function up(): JsonResponse
    {
        return response()->json([
            'status' => 'up',
            'message' => 'API is operational',
            'timestamp' => now()->toIso8601String(),
            'environment' => App::environment(),
            'version' => config('app.version', '1.0.0'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ]);
    }

    /**
     * Comprehensive health check endpoint.
     * Checks various system components and returns detailed status.
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $checks = [];
        $isHealthy = true;
        $startTime = microtime(true);

        // Database check
        $checks['database'] = $this->checkDatabase();
        if (!$checks['database']['healthy']) {
            $isHealthy = false;
        }

        // Cache check
        $checks['cache'] = $this->checkCache();
        if (!$checks['cache']['healthy']) {
            $isHealthy = false;
        }

        // Storage check
        $checks['storage'] = $this->checkStorage();
        if (!$checks['storage']['healthy']) {
            $isHealthy = false;
        }

        // Queue check (optional, only if queues are used)
        if (config('queue.default') !== 'sync') {
            $checks['queue'] = $this->checkQueue();
            if (!$checks['queue']['healthy']) {
                $isHealthy = false;
            }
        }

        // Memory usage check
        $checks['memory'] = $this->checkMemory();
        if (!$checks['memory']['healthy']) {
            $isHealthy = false;
        }

        // Disk space check
        $checks['disk'] = $this->checkDiskSpace();
        if (!$checks['disk']['healthy']) {
            $isHealthy = false;
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'environment' => App::environment(),
            'version' => config('app.version', '1.0.0'),
            'execution_time_ms' => $executionTime,
            'checks' => $checks
        ], $isHealthy ? 200 : 503);
    }

    /**
     * Check database connectivity and performance.
     *
     * @return array
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            // Check if response time is acceptable (under 100ms)
            $healthy = $responseTime < 100;

            return [
                'healthy' => $healthy,
                'message' => $healthy ? 'Database is responsive' : 'Database response time is high',
                'response_time_ms' => $responseTime,
                'connection' => config('database.default')
            ];
        } catch (\Exception $e) {
            Log::error('Health check database error: ' . $e->getMessage());
            return [
                'healthy' => false,
                'message' => 'Database connection failed',
                'error' => App::environment('production') ? 'Connection error' : $e->getMessage()
            ];
        }
    }

    /**
     * Check cache connectivity and performance.
     *
     * @return array
     */
    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            $value = 'test_' . uniqid();

            $start = microtime(true);
            Cache::put($key, $value, 10);
            $retrieved = Cache::get($key);
            Cache::forget($key);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            $healthy = $retrieved === $value && $responseTime < 50;

            return [
                'healthy' => $healthy,
                'message' => $healthy ? 'Cache is operational' : 'Cache performance issue',
                'response_time_ms' => $responseTime,
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            Log::error('Health check cache error: ' . $e->getMessage());
            return [
                'healthy' => false,
                'message' => 'Cache operation failed',
                'error' => App::environment('production') ? 'Cache error' : $e->getMessage()
            ];
        }
    }

    /**
     * Check storage accessibility.
     *
     * @return array
     */
    private function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            $content = 'Health check at ' . now();

            // Test write
            Storage::disk('local')->put($testFile, $content);

            // Test read
            $retrieved = Storage::disk('local')->get($testFile);

            // Clean up
            Storage::disk('local')->delete($testFile);

            $healthy = $retrieved === $content;

            return [
                'healthy' => $healthy,
                'message' => $healthy ? 'Storage is accessible' : 'Storage read/write failed',
                'disks' => [
                    'local' => Storage::disk('local')->exists(''),
                    'public' => Storage::disk('public')->exists('')
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Health check storage error: ' . $e->getMessage());
            return [
                'healthy' => false,
                'message' => 'Storage operation failed',
                'error' => App::environment('production') ? 'Storage error' : $e->getMessage()
            ];
        }
    }

    /**
     * Check queue connectivity (if applicable).
     *
     * @return array
     */
    private function checkQueue(): array
    {
        try {
            // This is a basic check. In production, you might want to
            // dispatch a test job and verify it processes
            $queueSize = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            $healthy = $failedJobs < 100; // Arbitrary threshold

            return [
                'healthy' => $healthy,
                'message' => $healthy ? 'Queue is operational' : 'Too many failed jobs',
                'pending_jobs' => $queueSize,
                'failed_jobs' => $failedJobs,
                'driver' => config('queue.default')
            ];
        } catch (\Exception $e) {
            // Queue tables might not exist
            return [
                'healthy' => true,
                'message' => 'Queue not configured',
                'driver' => config('queue.default')
            ];
        }
    }

    /**
     * Check memory usage.
     *
     * @return array
     */
    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $memoryUsagePercentage = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0;

        $healthy = $memoryUsagePercentage < 80;

        return [
            'healthy' => $healthy,
            'message' => $healthy ? 'Memory usage is acceptable' : 'High memory usage',
            'usage_mb' => round($memoryUsage / 1048576, 2),
            'limit_mb' => round($memoryLimit / 1048576, 2),
            'usage_percentage' => $memoryUsagePercentage
        ];
    }

    /**
     * Check disk space.
     *
     * @return array
     */
    private function checkDiskSpace(): array
    {
        $freeSpace = disk_free_space(storage_path());
        $totalSpace = disk_total_space(storage_path());
        $usedPercentage = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

        $healthy = $usedPercentage < 95;

        return [
            'healthy' => $healthy,
            'message' => $healthy ? 'Disk space is sufficient' : 'Low disk space',
            'free_gb' => round($freeSpace / 1073741824, 2),
            'total_gb' => round($totalSpace / 1073741824, 2),
            'used_percentage' => $usedPercentage
        ];
    }

    /**
     * Get memory limit in bytes.
     *
     * @return int
     */
    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit == -1) {
            return PHP_INT_MAX;
        }

        preg_match('/^(\d+)(.)$/', $memoryLimit, $matches);
        if (isset($matches[2])) {
            switch (strtoupper($matches[2])) {
                case 'G':
                    return $matches[1] * 1073741824;
                case 'M':
                    return $matches[1] * 1048576;
                case 'K':
                    return $matches[1] * 1024;
            }
        }

        return (int)$memoryLimit;
    }
}
