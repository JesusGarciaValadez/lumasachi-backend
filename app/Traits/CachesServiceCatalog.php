<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\OrderItemType;
use Illuminate\Support\Facades\Cache;

/**
 * Cache utilities for Service Catalog (engine options) with versioned keys.
 */
trait CachesServiceCatalog
{
    protected static function versionKey(): string
    {
        return 'service_catalog:version';
    }

    public static function currentVersion(): int
    {
        $key = self::versionKey();
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }
        return (int) Cache::get($key, 1);
    }

    public static function bumpVersion(): int
    {
        $key = self::versionKey();
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
            return 1;
        }
        return (int) Cache::increment($key);
    }

    public static function ttlEngineOptions(): int
    {
        return (int) config('cache.service_catalog.ttl.engine_options', 300);
    }

    public static function engineOptionsKey(string $locale, ?OrderItemType $itemType): string
    {
        $version = self::currentVersion();
        $type = $itemType?->value ?? 'all';
        return "service_catalog:engine_options:v{$version}:locale:{$locale}:type:{$type}";
    }
}
