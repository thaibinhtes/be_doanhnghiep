<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned catalog cache — bump version to invalidate without Cache::tags (works on DB/file cache).
 */
class CatalogCache
{
    public const BUCKET_NAV_MENU = 'nav_menu';

    public const BUCKET_HANH_CHINH = 'hanh_chinh_dm';

    public static function remember(string $bucket, string $key, int $ttlSeconds, Closure $callback): mixed
    {
        $version = self::version($bucket);

        return Cache::remember(
            self::entryKey($bucket, $version, $key),
            $ttlSeconds,
            $callback,
        );
    }

    public static function bump(string $bucket): void
    {
        $next = self::version($bucket) + 1;
        Cache::forever(self::versionKey($bucket), $next);
    }

    public static function version(string $bucket): int
    {
        return (int) Cache::get(self::versionKey($bucket), 1);
    }

    private static function versionKey(string $bucket): string
    {
        return "catalog_cache:{$bucket}:version";
    }

    private static function entryKey(string $bucket, int $version, string $key): string
    {
        return "catalog_cache:{$bucket}:v{$version}:{$key}";
    }
}
