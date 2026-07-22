<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Filter doanh nghiệp theo chiều địa bàn dashboard (group danh mục hành chính).
 * Match logic left-join trong DashboardService::buildCompanyAreaBreakdowns.
 */
class DoanhNghiepHanhChinhAreaFilter
{
    /**
     * @var array<string, array{table: string, loai: string, idColumn: string, textColumn: string, fallbackTextColumn: string, label: string}>
     */
    public const DIMENSIONS = [
        'quanHuyenMoi' => [
            'table' => 'hanh_chinh_quan_huyen',
            'loai' => 'moi',
            'idColumn' => 'quan_huyen_moi_id',
            'textColumn' => 'quan_huyen_moi',
            'fallbackTextColumn' => 'quan_huyen',
            'label' => 'Quận / Huyện mới',
        ],
        'quanHuyenCu' => [
            'table' => 'hanh_chinh_quan_huyen',
            'loai' => 'cu',
            'idColumn' => 'quan_huyen_cu_id',
            'textColumn' => 'quan_huyen_cu',
            'fallbackTextColumn' => 'quan_huyen',
            'label' => 'Quận / Huyện cũ',
        ],
        'phuongXaMoi' => [
            'table' => 'hanh_chinh_phuong_xa',
            'loai' => 'moi',
            'idColumn' => 'xa_phuong_moi_id',
            'textColumn' => 'xa_phuong_moi',
            'fallbackTextColumn' => 'phuong_xa',
            'label' => 'Phường / Xã / Thị trấn mới',
        ],
        'phuongXaCu' => [
            'table' => 'hanh_chinh_phuong_xa',
            'loai' => 'cu',
            'idColumn' => 'xa_phuong_cu_id',
            'textColumn' => 'xa_phuong_cu',
            'fallbackTextColumn' => 'phuong_xa',
            'label' => 'Phường / Xã / Thị trấn cũ',
        ],
    ];

    public static function apply(Builder $query, ?string $areaKey, ?string $areaId): Builder
    {
        if ($areaKey === null || $areaKey === '' || $areaId === null || $areaId === '') {
            return $query;
        }

        $dimension = self::DIMENSIONS[$areaKey] ?? null;
        if ($dimension === null || ! Schema::hasTable($dimension['table'])) {
            return $query;
        }

        $idColumn = Schema::hasColumn('doanh_nghieps', $dimension['idColumn'])
            ? $dimension['idColumn']
            : null;
        $textColumn = Schema::hasColumn('doanh_nghieps', $dimension['textColumn'])
            ? $dimension['textColumn']
            : $dimension['fallbackTextColumn'];
        $table = $dimension['table'];
        $loai = $dimension['loai'];

        if ($areaId === 'unlinked' || $areaId === 'null') {
            return $query->whereNotExists(function ($sub) use ($table, $loai, $idColumn, $textColumn) {
                self::matchAreaSubquery($sub, $table, $loai, $idColumn, $textColumn);
            });
        }

        if (! ctype_digit((string) $areaId)) {
            return $query;
        }

        $resolvedId = (int) $areaId;

        return $query->whereExists(function ($sub) use ($table, $loai, $idColumn, $textColumn, $resolvedId) {
            self::matchAreaSubquery($sub, $table, $loai, $idColumn, $textColumn);
            $sub->where('a.id', $resolvedId);
        });
    }

    private static function matchAreaSubquery($sub, string $table, string $loai, ?string $idColumn, string $textColumn): void
    {
        $sub->selectRaw('1')
            ->from("{$table} as a")
            ->where('a.loai', $loai)
            ->where(function ($join) use ($idColumn, $textColumn) {
                if ($idColumn !== null) {
                    $join->whereColumn('a.id', "doanh_nghieps.{$idColumn}");
                }

                $join->orWhere(function ($textMatch) use ($idColumn, $textColumn) {
                    if ($idColumn !== null) {
                        $textMatch->whereNull("doanh_nghieps.{$idColumn}");
                    }
                    $textMatch
                        ->whereRaw("NULLIF(TRIM(COALESCE(doanh_nghieps.{$textColumn}, '')), '') IS NOT NULL")
                        ->whereRaw("LOWER(TRIM(a.ten)) = LOWER(TRIM(doanh_nghieps.{$textColumn}))");
                });
            });
    }
}
