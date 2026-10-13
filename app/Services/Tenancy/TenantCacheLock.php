<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use LogicException;

final class TenantCacheLock
{
    public function lock(string $name, int $seconds = 0, ?string $owner = null): Lock
    {
        $tenant = tenancy()->tenant;

        if (!$tenant instanceof Tenant) {
            throw new LogicException('Tenant cache locks require an initialized tenant context.');
        }

        return Cache::lock('tenant:' . $tenant->getTenantKey() . ':' . $name, $seconds, $owner);
    }
}
