<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache utilities for Attachment endpoints with versioned keys.
 */
trait CachesAttachments
{
    public static function currentVersion(): int
    {
        $key = self::versionKey();
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }

        Cache::add($key, 1); // Only sets if key doesn't exist (atomic)

        return (int) Cache::get($key, 1);
    }

    public static function bumpVersion(): int
    {
        $key = self::versionKey();
        // Atomic: add returns false if key exists, ensuring exactly-once initialization
        $existed = ! Cache::add($key, 0); // Start at 0 since we'll increment immediately
        $before = $existed ? (int) Cache::get($key) : 0;
        $after = (int) Cache::increment($key);

        if (app()->runningUnitTests()) {
            Log::debug('attachments:version bump', [
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
        return (int) config('cache.attachments.ttl.index', 120);
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

        return "attachments:index:v{$version}:f:{$signature}";
    }

    protected static function versionKey(): string
    {
        return 'attachments:version';
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
                if (str_contains($frame['class'], 'CachesAttachments')) {
                    continue;
                }

                return "{$frame['class']}::{$frame['function']}";
            }
        }

        return 'unknown';
    }
}
