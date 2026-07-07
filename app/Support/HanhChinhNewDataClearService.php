<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use App\Models\HanhChinhMapping;
use App\Models\XaPhuong;
use Illuminate\Support\Facades\DB;

class HanhChinhNewDataClearService
{
    /**
     * @return array{wards: int, mappings: int, companiesLinked: int}
     */
    public static function preview(): array
    {
        return [
            'wards' => XaPhuong::query()->count(),
            'mappings' => HanhChinhMapping::query()->count(),
            'companiesLinked' => DoanhNghiep::query()->whereNotNull('xa_phuong_code')->count(),
        ];
    }

    /**
     * Xóa toàn bộ đơn vị hành chính mới để import lại.
     * Mapping cũ→mới bị xóa theo (FK). Doanh nghiệp đã đồng bộ được reset mã mới.
     *
     * @return array{wards: int, mappings: int, companiesReset: int}
     */
    public static function clear(): array
    {
        return DB::transaction(function () {
            $wardCount = XaPhuong::query()->count();
            $mappingCount = HanhChinhMapping::query()->count();

            $companiesReset = DoanhNghiep::query()
                ->where(function ($query) {
                    $query
                        ->whereNotNull('xa_phuong_code')
                        ->orWhereNotNull('tinh_thanh_code')
                        ->orWhereNotNull('hanh_chinh_synced_at');
                })
                ->update([
                    'xa_phuong_code' => null,
                    'tinh_thanh_code' => null,
                    'hanh_chinh_synced_at' => null,
                ]);

            XaPhuong::query()->delete();

            return [
                'wards' => $wardCount,
                'mappings' => $mappingCount,
                'companiesReset' => $companiesReset,
            ];
        });
    }
}
