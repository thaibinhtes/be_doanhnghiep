<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class DashboardService
{
    /** @var list<string> */
    public const AREA_KEYS = ['quanHuyenMoi', 'quanHuyenCu', 'phuongXaMoi', 'phuongXaCu'];

    /**
     * Lightweight overview only (no area breakdowns / báo cáo nặng).
     *
     * @return array<string, mixed>
     */
    public function buildOverview(?User $user = null): array
    {
        $companyStats = $this->companyOverviewStats($user);
        $cooperativeStats = $this->cooperativeOverviewStats($user);

        return [
            'generatedAt' => now()->toIso8601String(),
            'overview' => [
                'totalCompanies' => $companyStats['total'],
                'identified' => $companyStats['daDinhDanh'],
                'notIdentified' => $companyStats['canRaSoat'] + $companyStats['chuaDinhDanh'],
                'canRaSoat' => $companyStats['canRaSoat'],
                'withCoordinates' => $companyStats['withCoordinates'],
            ],
            'identity' => [
                'daDinhDanh' => $companyStats['daDinhDanh'],
                'canRaSoat' => $companyStats['canRaSoat'],
                'chuaDinhDanh' => $companyStats['chuaDinhDanh'],
            ],
            'cooperativeOverview' => [
                'totalCooperatives' => $cooperativeStats['total'],
            ],
            'cooperativeIdentity' => [
                'daDinhDanh' => $cooperativeStats['daDinhDanh'],
                'canRaSoat' => $cooperativeStats['canRaSoat'],
                'chuaDinhDanh' => $cooperativeStats['chuaDinhDanh'],
            ],
            'areaOptions' => $this->areaOptions(),
        ];
    }

    /**
     * Backward-compatible entry: overview only (areas loaded via dedicated endpoints).
     *
     * @return array<string, mixed>
     */
    public function build(?User $user = null): array
    {
        return $this->buildOverview($user);
    }

    /**
     * @return array{areaKey: string, areas: array<int, array<string, mixed>>, generatedAt: string}
     */
    public function buildCompanyAreas(?User $user, string $areaKey): array
    {
        $areaKey = $this->assertAreaKey($areaKey);

        return [
            'areaKey' => $areaKey,
            'areas' => $this->buildCompanyAreaDimension($user, $areaKey),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{areaKey: string, areas: array<int, array<string, mixed>>, generatedAt: string}
     */
    public function buildCooperativeAreas(?User $user, string $areaKey): array
    {
        $areaKey = $this->assertAreaKey($areaKey);

        return [
            'areaKey' => $areaKey,
            'areas' => $this->buildCooperativeAreaDimension($user, $areaKey),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function areaOptions(): array
    {
        return [
            ['key' => 'phuongXaMoi', 'label' => 'Phường / Xã / Thị trấn mới'],
            ['key' => 'quanHuyenMoi', 'label' => 'Quận / Huyện mới'],
            ['key' => 'quanHuyenCu', 'label' => 'Quận / Huyện cũ'],
            ['key' => 'phuongXaCu', 'label' => 'Phường / Xã / Thị trấn cũ'],
        ];
    }

    /**
     * @return array{total: int, daDinhDanh: int, canRaSoat: int, chuaDinhDanh: int, withCoordinates: int}
     */
    private function companyOverviewStats(?User $user): array
    {
        $row = DoanhNghiepScopeHelper::query($user)
            ->leftJoin(
                'company_tax_managements as company_tax',
                'company_tax.doanh_nghiep_id',
                '=',
                'doanh_nghieps.id',
            )
            ->selectRaw('COUNT(DISTINCT doanh_nghieps.id) as total')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = 1 THEN doanh_nghieps.id END) as da_dinh_danh',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = 0 AND company_tax.id IS NOT NULL THEN doanh_nghieps.id END) as can_ra_soat',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = 0 AND company_tax.id IS NULL THEN doanh_nghieps.id END) as chua_dinh_danh',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN doanh_nghieps.long IS NOT NULL AND doanh_nghieps.lat IS NOT NULL THEN doanh_nghieps.id END) as with_coordinates',
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'daDinhDanh' => (int) ($row->da_dinh_danh ?? 0),
            'canRaSoat' => (int) ($row->can_ra_soat ?? 0),
            'chuaDinhDanh' => (int) ($row->chua_dinh_danh ?? 0),
            'withCoordinates' => (int) ($row->with_coordinates ?? 0),
        ];
    }

    /**
     * @return array{total: int, daDinhDanh: int, canRaSoat: int, chuaDinhDanh: int}
     */
    private function cooperativeOverviewStats(?User $user): array
    {
        $row = HopTacXaScopeHelper::query($user)
            ->leftJoin(
                'cooperative_tax_managements as cooperative_tax',
                'cooperative_tax.hop_tac_xa_id',
                '=',
                'hop_tac_xas.id',
            )
            ->selectRaw('COUNT(DISTINCT hop_tac_xas.id) as total')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN cooperative_tax.id IS NOT NULL AND cooperative_tax.is_active = 1 THEN hop_tac_xas.id END) as da_dinh_danh',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN cooperative_tax.id IS NOT NULL AND cooperative_tax.is_active = 0 THEN hop_tac_xas.id END) as can_ra_soat',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN cooperative_tax.id IS NULL THEN hop_tac_xas.id END) as chua_dinh_danh',
            )
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'daDinhDanh' => (int) ($row->da_dinh_danh ?? 0),
            'canRaSoat' => (int) ($row->can_ra_soat ?? 0),
            'chuaDinhDanh' => (int) ($row->chua_dinh_danh ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanyAreaDimension(?User $user, string $areaKey): array
    {
        $dimensions = [
            'quanHuyenMoi' => [
                'table' => 'hanh_chinh_quan_huyen',
                'loai' => 'moi',
                'idColumn' => 'quan_huyen_moi_id',
                'textColumn' => 'quan_huyen_moi',
                'fallbackTextColumn' => 'quan_huyen',
            ],
            'quanHuyenCu' => [
                'table' => 'hanh_chinh_quan_huyen',
                'loai' => 'cu',
                'idColumn' => 'quan_huyen_cu_id',
                'textColumn' => 'quan_huyen_cu',
                'fallbackTextColumn' => 'quan_huyen',
            ],
            'phuongXaMoi' => [
                'table' => 'hanh_chinh_phuong_xa',
                'loai' => 'moi',
                'idColumn' => 'xa_phuong_moi_id',
                'textColumn' => 'xa_phuong_moi',
                'fallbackTextColumn' => 'phuong_xa',
            ],
            'phuongXaCu' => [
                'table' => 'hanh_chinh_phuong_xa',
                'loai' => 'cu',
                'idColumn' => 'xa_phuong_cu_id',
                'textColumn' => 'xa_phuong_cu',
                'fallbackTextColumn' => 'phuong_xa',
            ],
        ];

        $dimension = $dimensions[$areaKey];
        if (! Schema::hasTable($dimension['table'])) {
            return [];
        }

        $idColumn = Schema::hasColumn('doanh_nghieps', $dimension['idColumn'])
            ? $dimension['idColumn']
            : null;
        $textColumn = Schema::hasColumn('doanh_nghieps', $dimension['textColumn'])
            ? $dimension['textColumn']
            : $dimension['fallbackTextColumn'];

        $rows = DoanhNghiepScopeHelper::query($user)
            ->leftJoin("{$dimension['table']} as admin_area", function ($join) use ($dimension, $idColumn, $textColumn) {
                $loai = $dimension['loai'];
                if ($idColumn !== null) {
                    $join->whereRaw(
                        "admin_area.loai = ?
                        AND (
                            admin_area.id = doanh_nghieps.{$idColumn}
                            OR (
                                doanh_nghieps.{$idColumn} IS NULL
                                AND NULLIF(TRIM(COALESCE(doanh_nghieps.{$textColumn}, '')), '') IS NOT NULL
                                AND LOWER(TRIM(admin_area.ten)) = LOWER(TRIM(doanh_nghieps.{$textColumn}))
                            )
                        )",
                        [$loai],
                    );
                } else {
                    $join->whereRaw(
                        "admin_area.loai = ?
                        AND NULLIF(TRIM(COALESCE(doanh_nghieps.{$textColumn}, '')), '') IS NOT NULL
                        AND LOWER(TRIM(admin_area.ten)) = LOWER(TRIM(doanh_nghieps.{$textColumn}))",
                        [$loai],
                    );
                }
            })
            ->leftJoin(
                'company_tax_managements as company_tax',
                'company_tax.doanh_nghiep_id',
                '=',
                'doanh_nghieps.id',
            )
            ->selectRaw('admin_area.id as area_code, admin_area.ten as area_name')
            ->selectRaw('COUNT(DISTINCT doanh_nghieps.id) as total')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = true THEN doanh_nghieps.id END) as da_dinh_danh',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = false AND company_tax.id IS NOT NULL THEN doanh_nghieps.id END) as can_ra_soat',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = false AND company_tax.id IS NULL THEN doanh_nghieps.id END) as chua_dinh_danh',
            )
            ->groupBy('admin_area.id', 'admin_area.ten')
            ->get();

        return $this->formatUnifiedAreaStats($rows, $dimension['table'], $dimension['loai']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCooperativeAreaDimension(?User $user, string $areaKey): array
    {
        if (! Schema::hasTable('hanh_chinh_phuong_xa')) {
            return [];
        }

        $wardTextColumn = Schema::hasColumn('hop_tac_xas', 'phuong_xa') ? 'phuong_xa' : null;
        if ($wardTextColumn === null) {
            return [];
        }

        return match ($areaKey) {
            'phuongXaCu' => $this->formatUnifiedAreaStats(
                $this->selectCooperativeAreaCounts(
                    $this->buildCooperativeAreaQuery($user)
                        ->leftJoin('hanh_chinh_phuong_xa as admin_area', function ($join) use ($wardTextColumn) {
                            $join->where('admin_area.loai', '=', 'cu')
                                ->whereRaw(
                                    'LOWER(TRIM(admin_area.ten)) = LOWER(TRIM(hop_tac_xas.'.$wardTextColumn.'))',
                                );
                        }),
                    'admin_area',
                )->get(),
                'hanh_chinh_phuong_xa',
                'cu',
            ),
            'phuongXaMoi' => $this->formatUnifiedAreaStats(
                $this->selectCooperativeAreaCounts(
                    $this->buildCooperativeAreaQuery($user)
                        ->leftJoin('hanh_chinh_phuong_xa as admin_area', function ($join) use ($wardTextColumn) {
                            $join->where('admin_area.loai', '=', 'moi')
                                ->whereRaw(
                                    'LOWER(TRIM(admin_area.ten)) = LOWER(TRIM(hop_tac_xas.'.$wardTextColumn.'))',
                                );
                        }),
                    'admin_area',
                )->get(),
                'hanh_chinh_phuong_xa',
                'moi',
            ),
            'quanHuyenCu' => Schema::hasTable('hanh_chinh_quan_huyen')
                ? $this->formatUnifiedAreaStats(
                    $this->selectCooperativeAreaCounts(
                        $this->buildCooperativeAreaQuery($user)
                            ->leftJoin('hanh_chinh_phuong_xa as matched_ward', function ($join) use ($wardTextColumn) {
                                $join->where('matched_ward.loai', '=', 'cu')
                                    ->whereRaw(
                                        'LOWER(TRIM(matched_ward.ten)) = LOWER(TRIM(hop_tac_xas.'.$wardTextColumn.'))',
                                    );
                            })
                            ->leftJoin('hanh_chinh_quan_huyen as admin_area', function ($join) {
                                $join->on('admin_area.id', '=', 'matched_ward.quan_huyen_id')
                                    ->where('admin_area.loai', '=', 'cu');
                            }),
                        'admin_area',
                    )->get(),
                    'hanh_chinh_quan_huyen',
                    'cu',
                )
                : [],
            'quanHuyenMoi' => Schema::hasTable('hanh_chinh_quan_huyen')
                ? $this->formatUnifiedAreaStats(
                    $this->selectCooperativeAreaCounts(
                        $this->buildCooperativeAreaQuery($user)
                            ->leftJoin('hanh_chinh_phuong_xa as matched_ward', function ($join) use ($wardTextColumn) {
                                $join->where('matched_ward.loai', '=', 'moi')
                                    ->whereRaw(
                                        'LOWER(TRIM(matched_ward.ten)) = LOWER(TRIM(hop_tac_xas.'.$wardTextColumn.'))',
                                    );
                            })
                            ->leftJoin('hanh_chinh_quan_huyen as admin_area', function ($join) {
                                $join->on('admin_area.id', '=', 'matched_ward.quan_huyen_id')
                                    ->where('admin_area.loai', '=', 'moi');
                            }),
                        'admin_area',
                    )->get(),
                    'hanh_chinh_quan_huyen',
                    'moi',
                )
                : [],
            default => [],
        };
    }

    private function assertAreaKey(string $areaKey): string
    {
        if (! in_array($areaKey, self::AREA_KEYS, true)) {
            throw new InvalidArgumentException('areaKey không hợp lệ.');
        }

        return $areaKey;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatUnifiedAreaStats($rows, string $catalogTable, string $loai): array
    {
        $countsByCode = $rows
            ->filter(fn ($row) => $row->area_code !== null)
            ->keyBy(fn ($row) => (string) $row->area_code);

        $catalogRows = DB::table($catalogTable)
            ->where('loai', $loai)
            ->select(['id', 'ten'])
            ->orderBy('ten')
            ->get()
            ->map(function ($area) use ($countsByCode) {
                $stats = $countsByCode->get((string) $area->id);

                return [
                    'areaCode' => (string) $area->id,
                    'areaName' => (string) $area->ten,
                    'total' => (int) ($stats->total ?? 0),
                    'daDinhDanh' => (int) ($stats->da_dinh_danh ?? 0),
                    'canRaSoat' => (int) ($stats->can_ra_soat ?? 0),
                    'chuaDinhDanh' => (int) ($stats->chua_dinh_danh ?? 0),
                ];
            });

        $unlinked = $rows->first(fn ($row) => $row->area_code === null);
        if ($unlinked && (int) $unlinked->total > 0) {
            $catalogRows->push([
                'areaCode' => null,
                'areaName' => 'Chưa liên kết hành chính',
                'total' => (int) $unlinked->total,
                'daDinhDanh' => (int) $unlinked->da_dinh_danh,
                'canRaSoat' => (int) $unlinked->can_ra_soat,
                'chuaDinhDanh' => (int) $unlinked->chua_dinh_danh,
            ]);
        }

        return $catalogRows->values()->all();
    }

    private function buildCooperativeAreaQuery(?User $user)
    {
        return HopTacXaScopeHelper::query($user)
            ->leftJoin(
                'cooperative_tax_managements as cooperative_tax',
                'cooperative_tax.hop_tac_xa_id',
                '=',
                'hop_tac_xas.id',
            );
    }

    private function selectCooperativeAreaCounts($query, string $areaAlias)
    {
        return $query
            ->selectRaw("{$areaAlias}.id as area_code, {$areaAlias}.ten as area_name")
            ->selectRaw('COUNT(DISTINCT hop_tac_xas.id) as total')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN cooperative_tax.id IS NOT NULL AND cooperative_tax.is_active = true THEN hop_tac_xas.id END) as da_dinh_danh',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN cooperative_tax.id IS NOT NULL AND cooperative_tax.is_active = false THEN hop_tac_xas.id END) as can_ra_soat',
            )
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN cooperative_tax.id IS NULL THEN hop_tac_xas.id END) as chua_dinh_danh',
            )
            ->groupBy("{$areaAlias}.id", "{$areaAlias}.ten");
    }
}
