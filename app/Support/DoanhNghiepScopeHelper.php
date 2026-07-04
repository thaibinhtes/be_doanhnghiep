<?php

namespace App\Support;

use App\Models\DonVi;
use App\Models\DoanhNghiep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DoanhNghiepScopeHelper
{
    /**
     * @return array<int, int>|null null = không giới hạn theo đơn vị (user thuộc ROOT)
     */
    public static function allowedDonViIds(?User $user): ?array
    {
        if ($user === null || DonVi::userBelongsToRoot($user)) {
            return null;
        }

        if ($user->don_vi_id === null) {
            return [];
        }

        return DonVi::idsWithDescendants((int) $user->don_vi_id);
    }

    public static function query(?User $user = null): Builder
    {
        $user ??= auth()->user();

        return self::applyScope(DoanhNghiep::query(), $user);
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

    public static function userCanAccess(?User $user, DoanhNghiep $doanhNghiep): bool
    {
        $allowedIds = self::allowedDonViIds($user);

        if ($allowedIds === null) {
            return true;
        }

        if ($allowedIds === [] || $doanhNghiep->don_vi_id === null) {
            return false;
        }

        return in_array((int) $doanhNghiep->don_vi_id, $allowedIds, true);
    }

    /**
     * @return array<int, int>|null
     */
    public static function resolveDonViFilterIds(?User $user, ?int $requestedDonViId): ?array
    {
        if ($requestedDonViId === null) {
            return self::allowedDonViIds($user);
        }

        $requestedIds = DonVi::idsWithDescendants($requestedDonViId);
        $allowedIds = self::allowedDonViIds($user);

        if ($allowedIds === null) {
            return $requestedIds;
        }

        return array_values(array_intersect($requestedIds, $allowedIds));
    }

    /**
     * @param  array<int, int>  $companyIds
     * @return array<int, int>
     */
    public static function filterAccessibleCompanyIds(?User $user, array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return self::query($user)
            ->whereIn('id', $companyIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
