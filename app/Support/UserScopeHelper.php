<?php

namespace App\Support;

use App\Models\DonVi;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class UserScopeHelper
{
    /**
     * @return array<int, int>|null null = không giới hạn (ROOT role)
     */
    public static function allowedDonViIds(?User $user): ?array
    {
        if ($user === null) {
            return [];
        }

        if (RoleHierarchyHelper::isRootUser($user)) {
            return null;
        }

        if ($user->don_vi_id === null) {
            return [];
        }

        return DonVi::idsWithDescendants((int) $user->don_vi_id);
    }

    /** @return Builder<User> */
    public static function query(?User $user = null): Builder
    {
        $user ??= auth()->user();

        return self::applyScope(User::query(), $user);
    }

    /** @param Builder<User> $query */
    public static function applyScope(Builder $query, ?User $user): Builder
    {
        $allowedIds = self::allowedDonViIds($user);

        if ($allowedIds === null) {
            return $query;
        }

        if ($allowedIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('don_vi_id', $allowedIds);
    }

    public static function userCanAccess(?User $actor, User $target): bool
    {
        $allowedIds = self::allowedDonViIds($actor);

        if ($allowedIds === null) {
            return true;
        }

        if ($target->don_vi_id === null) {
            return false;
        }

        return in_array((int) $target->don_vi_id, $allowedIds, true);
    }

    public static function resolveDonViIdForCreate(?User $actor, ?int $requestedDonViId): ?int
    {
        if ($actor === null) {
            return null;
        }

        if (RoleHierarchyHelper::isRootUser($actor)) {
            return $requestedDonViId ?? $actor->don_vi_id;
        }

        return $actor->don_vi_id;
    }

    public static function canChangeDonVi(?User $actor): bool
    {
        return RoleHierarchyHelper::isRootUser($actor);
    }

    public static function donViIdIsAllowed(?User $actor, ?int $donViId): bool
    {
        if ($donViId === null) {
            return RoleHierarchyHelper::isRootUser($actor);
        }

        $allowedIds = self::allowedDonViIds($actor);

        if ($allowedIds === null) {
            return true;
        }

        return in_array($donViId, $allowedIds, true);
    }
}
