<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Local (file) caches for auth profile & role permission keys.
 * Avoids repeated remote MySQL round-trips for /auth/me and permission checks.
 */
class AuthProfileCache
{
    private const STORE = 'file';

    private const ME_TTL = 600;

    private const ROLE_KEYS_TTL = 3600;

    /** @return array<int, string> */
    public static function permissionKeysForRole(?int $roleId): array
    {
        if ($roleId === null || $roleId <= 0) {
            return [];
        }

        $version = CatalogCache::version(CatalogCache::BUCKET_NAV_MENU);

        return Cache::store(self::STORE)->remember(
            "auth:role_perm_keys:{$roleId}:v{$version}",
            self::ROLE_KEYS_TTL,
            function () use ($roleId) {
                return DB::table('permission_role')
                    ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                    ->where('permission_role.role_id', $roleId)
                    ->orderBy('permissions.key')
                    ->pluck('permissions.key')
                    ->map(fn ($key) => (string) $key)
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public static function rememberMe(int $userId, string $fingerprint, callable $callback): array
    {
        $version = CatalogCache::version(CatalogCache::BUCKET_NAV_MENU);

        return Cache::store(self::STORE)->remember(
            "auth:me:{$userId}:{$fingerprint}:v{$version}",
            self::ME_TTL,
            $callback,
        );
    }

    public static function forgetUser(int $userId): void
    {
        // Fingerprint changes with updated_at; bump nav_menu version also invalidates.
        // Clear known recent keys by bumping a user-specific marker used in fingerprint optionally.
        Cache::store(self::STORE)->forever("auth:me:user:{$userId}:bust", (string) microtime(true));
    }

    public static function userBustToken(int $userId): string
    {
        return (string) Cache::store(self::STORE)->get("auth:me:user:{$userId}:bust", '0');
    }
}
