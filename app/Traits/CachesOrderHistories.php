<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache utilities for OrderHistory endpoints with versioned keys.
 */
trait CachesOrderHistories
{
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
        $existed = Cache::has($key);
        $before = $existed ? (int) Cache::get($key) : null;

        if (! $existed) {
            Cache::forever($key, 1);
            $after = 1;
        } else {
            $after = (int) Cache::increment($key);
        }

        if (app()->runningUnitTests()) {
            Log::debug('order_histories:version bump', [
                'caller' => self::caller(),
                'existed' => $existed,
                'before' => $before,
                'after' => $after,
            ]);
        }

        return $after;
    }

    public static function ttlIndex(): int
    {
        return (int) config('cache.order_histories.ttl.index', 120);
    }

    public static function ttlShow(): int
    {
        return (int) config('cache.order_histories.ttl.show', 300);
    }

    /**
     * Build cache key for index with normalized filters and pagination.
     *
     * @param  array<string,mixed>  $filters
     */
    public static function indexKeyFor(array $filters = []): string
    {
        $version = self::currentVersion();
        // Normalize and hash filters to keep keys short and unique
        ksort($filters);
        $signature = md5(json_encode($filters));

        return "order_histories:index:v{$version}:f:{$signature}";
    }

    public static function showKeyFor(string $uuid): string
    {
        $version = self::currentVersion();

        return "order_histories:show:v{$version}:uuid:{$uuid}";
    }

    protected static function versionKey(): string
    {
        return 'order_histories:version';
    }

    /**
     * Resolve the likely caller for debugging purposes during tests.
     */
    protected static function caller(): string
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);

        foreach ($bt as $frame) {
            if (isset($frame['class'], $frame['function'])) {
                // Skip internal cache trait frames
                if (str_contains($frame['class'], __CLASS__)) {
                    continue;
                }

                return "{$frame['class']}::{$frame['function']}";
            }
        }

        return 'unknown';
    }
}
