<?php

namespace App\Support;

use App\Models\HopTacXa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class HopTacXaScopeHelper
{
    /**
     * @return array<int, int>|null
     */
    public static function allowedDonViIds(?User $user): ?array
    {
        if ($user === null || DoanhNghiepScopeHelper::hasUnrestrictedScope($user)) {
            return null;
        }

        if ($user->don_vi_id === null) {
            return [];
        }

        return \App\Models\DonVi::idsWithDescendants((int) $user->don_vi_id);
    }

    public static function query(?User $user = null): Builder
    {
        $user ??= auth()->user();

        return self::applyScope(HopTacXa::query(), $user);
    }

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

    public static function userCanAccess(?User $user, HopTacXa $hopTacXa): bool
    {
        $allowedIds = self::allowedDonViIds($user);

        if ($allowedIds === null) {
            return true;
        }

        if ($allowedIds === [] || $hopTacXa->don_vi_id === null) {
            return false;
        }

        return in_array((int) $hopTacXa->don_vi_id, $allowedIds, true);
    }

    /**
     * @return array<int, int>|null
     */
    public static function resolveDonViFilterIds(?User $user, ?int $requestedDonViId): ?array
    {
        if ($requestedDonViId === null) {
            return self::allowedDonViIds($user);
        }

        $requestedIds = \App\Models\DonVi::idsWithDescendants($requestedDonViId);
        $allowedIds = self::allowedDonViIds($user);

        if ($allowedIds === null) {
            return $requestedIds;
        }

        return array_values(array_intersect($requestedIds, $allowedIds));
    }
}
