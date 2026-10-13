<?php

declare(strict_types=1);

namespace Tests\Support\Tenancy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class TenantWorkerContextProbeJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /** @var list<array{label: string, tenant_id: string|null, database: string, cache_value: string|null}> */
    public static array $observations = [];

    public function __construct(
        public string $label,
        public bool   $throwAfterRecording = false,
    )
    {
    }

    public function handle(): void
    {
        $tenant = tenant();
        $tenantId = $tenant?->getTenantKey();
        $databaseName = DB::connection('tenant')->selectOne(
            'select current_database() as database_name',
        )->database_name;

        if (!is_string($tenantId) || !is_string($databaseName)) {
            throw new RuntimeException('The queued probe job did not resolve its initialized tenant database.');
        }

        Cache::put('worker-context-probe', $tenantId, now()->addMinute());
        $cacheValue = Cache::get('worker-context-probe');

        if (!is_string($cacheValue)) {
            throw new RuntimeException('The tenant cache probe did not return its stored value.');
        }

        self::$observations[] = [
            'label' => $this->label,
            'tenant_id' => $tenantId,
            'database' => $databaseName,
            'cache_value' => $cacheValue,
        ];

        if ($this->throwAfterRecording) {
            throw new RuntimeException('Simulated tenant queue job failure.');
        }
    }
}
