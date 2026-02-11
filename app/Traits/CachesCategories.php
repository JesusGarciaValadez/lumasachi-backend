<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

/**
 * Centralized cache utilities for Category endpoints.
 */
trait CachesCategories
{
    public static function currentVersion(): int
    {
        $key = self::versionKey();
        Cache::add($key, 1);

        return (int) Cache::get($key, 1);
    }

    public static function bumpVersion(): int
    {
        $key = self::versionKey();
        Cache::add($key, 0);

        return (int) Cache::increment($key);
    }

    public static function ttlIndex(): int
    {
        return (int) config('cache.categories.ttl.index', 300);
    }

    public static function indexKeyForCompany(int $companyId): string
    {
        $version = self::currentVersion();

        return "categories:index:v{$version}:company:{$companyId}";
    }

    protected static function versionKey(): string
    {
        return 'categories:version';
    }
}
