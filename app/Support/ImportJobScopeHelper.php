<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ImportJobScopeHelper
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyScope(Builder $query, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $allowedIds = DoanhNghiepScopeHelper::allowedDonViIds($user);

        if ($allowedIds === null) {
            return $query;
        }

        if ($allowedIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($allowedIds, $user): void {
            $builder->whereIn('don_vi_id', $allowedIds);

            if ($user !== null) {
                $builder->orWhere(function (Builder $legacy) use ($user): void {
                    $legacy
                        ->whereNull('don_vi_id')
                        ->where('user_id', $user->id);
                });
            }
        });
    }

    public static function userCanAccess(?User $user, Model $importJob): bool
    {
        if ($user === null) {
            return false;
        }

        if (DoanhNghiepScopeHelper::hasUnrestrictedScope($user)) {
            return true;
        }

        $donViId = $importJob->getAttribute('don_vi_id');
        if ($donViId !== null) {
            $allowedIds = DoanhNghiepScopeHelper::allowedDonViIds($user);

            return is_array($allowedIds) && in_array((int) $donViId, $allowedIds, true);
        }

        return (int) $importJob->getAttribute('user_id') === (int) $user->id;
    }

    public static function resolveDonViId(?User $user): ?int
    {
        return DoanhNghiepScopeHelper::resolveAssignmentDonViId($user);
    }
}
