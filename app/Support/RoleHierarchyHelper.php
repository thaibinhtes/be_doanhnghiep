<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RoleHierarchyHelper
{
    public const SLUG_ROOT = 'root';

    public static function roleLevel(?Role $role): int
    {
        return (int) ($role?->level ?? 0);
    }

    public static function isRootUser(?User $user): bool
    {
        return $user?->role?->slug === self::SLUG_ROOT;
    }

    public static function canAssignRole(?User $actor, ?Role $targetRole): bool
    {
        if ($actor === null || $targetRole === null) {
            return false;
        }

        if (self::isRootUser($actor)) {
            return self::roleLevel($targetRole) < self::roleLevel($actor->role);
        }

        return self::roleLevel($targetRole) < self::roleLevel($actor->role);
    }

    public static function canManageRole(?User $actor, Role $targetRole): bool
    {
        if ($actor === null) {
            return false;
        }

        if (self::isRootUser($actor)) {
            return self::roleLevel($targetRole) < self::roleLevel($actor->role);
        }

        return self::roleLevel($targetRole) < self::roleLevel($actor->role);
    }

    public static function canManageUser(?User $actor, User $target): bool
    {
        if ($actor === null) {
            return false;
        }

        if ((int) $actor->id === (int) $target->id) {
            return true;
        }

        if (!UserScopeHelper::userCanAccess($actor, $target)) {
            return false;
        }

        if (self::isRootUser($actor)) {
            return true;
        }

        return self::roleLevel($target->role) < self::roleLevel($actor->role);
    }

    /** @return Builder<Role> */
    public static function assignableRolesQuery(?User $actor): Builder
    {
        $query = Role::query()->orderByDesc('level');

        if ($actor === null) {
            return $query->whereRaw('1 = 0');
        }

        if (self::isRootUser($actor)) {
            return $query->where('level', '<', self::roleLevel($actor->role));
        }

        return $query->where('level', '<', self::roleLevel($actor->role));
    }

    /** @return Builder<Role> */
    public static function visibleRolesQuery(?User $actor): Builder
    {
        return self::assignableRolesQuery($actor);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function filterGrantablePermissionKeys(?User $actor, array $keys): array
    {
        if ($actor === null) {
            return [];
        }

        if (self::isRootUser($actor)) {
            return array_values(array_unique($keys));
        }

        $allowed = $actor->permissionKeys();

        return array_values(array_intersect($keys, $allowed));
    }

    /**
     * @param  array<int, string>  $keys
     */
    public static function assertCanGrantPermissions(?User $actor, array $keys): ?string
    {
        if ($actor === null) {
            return 'Không xác định được người thực hiện.';
        }

        if (self::isRootUser($actor)) {
            return null;
        }

        $allowed = $actor->permissionKeys();
        foreach ($keys as $key) {
            if (!in_array($key, $allowed, true)) {
                return "Không có quyền gán quyền: {$key}";
            }
        }

        return null;
    }
}
