<?php

namespace App\Support;

use App\Models\DnDinhDanhLichSu;
use App\Models\DoanhNghiep;
use App\Models\DonVi;
use App\Models\HopTacXa;
use App\Models\MemberCompany;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DonViDataClearService
{
    public static function assertCanClearByDonVi(?User $user): void
    {
        if (!DoanhNghiepScopeHelper::hasUnrestrictedScope($user)) {
            abort(403, 'Chỉ quản trị viên ROOT mới được xóa toàn bộ dữ liệu theo đơn vị.');
        }
    }

    /**
     * @return array{donViId: int, donViIds: array<int, int>, count: int}
     */
    public static function previewDoanhNghiep(int $donViId): array
    {
        $donViIds = DonVi::idsWithDescendants($donViId);
        $count = DoanhNghiep::query()->whereIn('don_vi_id', $donViIds)->count();

        return [
            'donViId' => $donViId,
            'donViIds' => $donViIds,
            'count' => $count,
        ];
    }

    /**
     * @return array{donViId: int, donViIds: array<int, int>, count: int}
     */
    public static function previewHopTacXa(int $donViId): array
    {
        $donViIds = DonVi::idsWithDescendants($donViId);
        $count = HopTacXa::query()->whereIn('don_vi_id', $donViIds)->count();

        return [
            'donViId' => $donViId,
            'donViIds' => $donViIds,
            'count' => $count,
        ];
    }

    /**
     * @return array{deleted: int, donViId: int, donViIds: array<int, int>}
     */
    public static function clearDoanhNghiep(int $donViId): array
    {
        $donViIds = DonVi::idsWithDescendants($donViId);

        $deleted = DB::transaction(function () use ($donViIds) {
            $companyIds = DoanhNghiep::query()
                ->whereIn('don_vi_id', $donViIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($companyIds === []) {
                return 0;
            }

            DnDinhDanhLichSu::query()->whereIn('doanh_nghiep_id', $companyIds)->delete();
            MemberCompany::query()->whereIn('doanh_nghiep_id', $companyIds)->delete();

            return DoanhNghiep::query()->whereIn('id', $companyIds)->delete();
        });

        return [
            'deleted' => $deleted,
            'donViId' => $donViId,
            'donViIds' => $donViIds,
        ];
    }

    /**
     * @return array{deleted: int, donViId: int, donViIds: array<int, int>}
     */
    public static function clearHopTacXa(int $donViId): array
    {
        $donViIds = DonVi::idsWithDescendants($donViId);

        $deleted = HopTacXa::query()
            ->whereIn('don_vi_id', $donViIds)
            ->delete();

        return [
            'deleted' => $deleted,
            'donViId' => $donViId,
            'donViIds' => $donViIds,
        ];
    }
}
