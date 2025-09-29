<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Centralized cache utilities for Order endpoints.
 */
trait CachesOrders
{
    /**
     * The global version cache key for Orders cache namespace.
     */
    protected static function versionKey(): string
    {
        return 'orders:version';
    }

    /**
     * Get current global version for Orders cache keys.
     */
    public static function currentVersion(): int
    {
        $key = self::versionKey();

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        return (int) Cache::get($key, 1);
    }

    /**
     * Increment the global cache version for Orders (invalidates all derived keys).
     *
     * @return int The new version.
     */
    public static function bumpVersion(): int
    {
        $key = self::versionKey();

        if (! Cache::has($key)) {
            Cache::forever($key, 1);
            return 1;
        }

        return (int) Cache::increment($key);
    }

    /**
     * TTL in seconds for index endpoint.
     */
    public static function ttlIndex(): int
    {
        return (int) config('cache.orders.ttl.index', 60);
    }

    /**
     * TTL in seconds for show endpoint.
     */
    public static function ttlShow(): int
    {
        return (int) config('cache.orders.ttl.show', 300);
    }

    /**
     * Build cache key for listing orders for a user and optional filters.
     *
     * @param User $user
     * @param array<string, mixed> $filters
     */
    public static function indexKeyFor(User $user, array $filters = []): string
    {
        $version = self::currentVersion();

        // Determine role slug using helper methods
        $roleSlug = 'unknown';
        if (method_exists($user, 'isCustomer') && $user->isCustomer()) {
            $roleSlug = 'customer';
        } elseif (method_exists($user, 'isEmployee') && $user->isEmployee()) {
            $roleSlug = 'employee';
        } elseif (method_exists($user, 'isAdministrator') && $user->isAdministrator()) {
            $roleSlug = 'administrator';
        } elseif (method_exists($user, 'isSuperAdministrator') && $user->isSuperAdministrator()) {
            $roleSlug = 'super_administrator';
        }

        if (!empty($filters)) {
            ksort($filters);
        }
        $filtersPart = !empty($filters) ? ':filters:' . md5(json_encode($filters)) : '';

        return "orders:index:v{$version}:role:{$roleSlug}:user:{$user->id}{$filtersPart}";
    }

    /**
     * Build cache key for a single order by UUID.
     */
    public static function showKeyFor(string $uuid): string
    {
        $version = self::currentVersion();

        return "orders:show:v{$version}:uuid:{$uuid}";
    }
}
