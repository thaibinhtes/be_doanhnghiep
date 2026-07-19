<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function __construct(
        private readonly BaoCaoTongHopService $baoCaoTongHopService,
        private readonly BaoCaoTongHopHtxService $baoCaoTongHopHtxService,
        private readonly BaoCaoTienDoDinhDanhService $tienDoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user = null): array
    {
        $summary = $this->baoCaoTongHopService->build($user);
        $cooperativeSummary = $this->baoCaoTongHopHtxService->build($user);
        $progress = $this->tienDoService->build([], $user);

        $companyQuery = DoanhNghiepScopeHelper::query($user);
        $totalCompanies = (clone $companyQuery)->count();
        $identified = (clone $companyQuery)->where('da_cap_nhat_dinh_danh', true)->count();
        $notIdentifiedQuery = (clone $companyQuery)->where('da_cap_nhat_dinh_danh', false);
        $canRaSoat = (clone $notIdentifiedQuery)->whereHas('taxManagement')->count();
        $chuaDinhDanh = (clone $notIdentifiedQuery)->whereDoesntHave('taxManagement')->count();
        $notIdentified = $canRaSoat + $chuaDinhDanh;
        $withCoordinates = (clone $companyQuery)
            ->whereNotNull('long')
            ->whereNotNull('lat')
            ->count();

        $cooperativeQuery = HopTacXaScopeHelper::query($user);
        $totalCooperatives = (clone $cooperativeQuery)->count();
        $cooperativeDaDinhDanh = (clone $cooperativeQuery)
            ->whereHas('taxManagement', fn ($query) => $query->where('is_active', true))
            ->count();
        $cooperativeCanRaSoat = (clone $cooperativeQuery)
            ->whereHas('taxManagement', fn ($query) => $query->where('is_active', false))
            ->count();
        $cooperativeChuaDinhDanh = (clone $cooperativeQuery)
            ->whereDoesntHave('taxManagement')
            ->count();

        $progressTotalRow = collect($progress['rows'] ?? [])
            ->firstWhere('key', 'tong_cong');
        $companyAreaBreakdowns = $this->buildCompanyAreaBreakdowns($user);
        $cooperativeAreaBreakdowns = $this->buildCooperativeAreaBreakdowns($user);

        return [
            'generatedAt' => now()->toIso8601String(),
            'overview' => [
                'totalCompanies' => $totalCompanies,
                'identified' => $identified,
                'notIdentified' => $notIdentified,
                'canRaSoat' => $canRaSoat,
                'withCoordinates' => $withCoordinates,
            ],
            'identity' => [
                'daDinhDanh' => $identified,
                'canRaSoat' => $canRaSoat,
                'chuaDinhDanh' => $chuaDinhDanh,
            ],
            'cooperativeOverview' => [
                'totalCooperatives' => $totalCooperatives,
            ],
            'cooperativeIdentity' => [
                'daDinhDanh' => $cooperativeDaDinhDanh,
                'canRaSoat' => $cooperativeCanRaSoat,
                'chuaDinhDanh' => $cooperativeChuaDinhDanh,
            ],
            'areaOptions' => [
                ['key' => 'quanHuyenMoi', 'label' => 'Quận / Huyện mới'],
                ['key' => 'quanHuyenCu', 'label' => 'Quận / Huyện cũ'],
                ['key' => 'phuongXaMoi', 'label' => 'Phường / Xã / Thị trấn mới'],
                ['key' => 'phuongXaCu', 'label' => 'Phường / Xã / Thị trấn cũ'],
            ],
            'companyAreaBreakdowns' => $companyAreaBreakdowns,
            'cooperativeAreaBreakdowns' => $cooperativeAreaBreakdowns,
            'summary' => $summary,
            'cooperativeSummary' => $cooperativeSummary,
            'progress' => [
                'title' => $progress['title'] ?? '',
                'reportDateLabel' => $progress['reportDateLabel'] ?? '',
                'ranges' => $progress['ranges'] ?? [],
                'metricLabels' => $progress['metricLabels'] ?? [],
                'totalRow' => $progressTotalRow,
            ],
        ];
    }

    /**
     * Group theo danh mục hành chính hợp nhất (hanh_chinh_*), ưu tiên cột id đã sync.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildCompanyAreaBreakdowns(?User $user): array
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

        $result = [];

        foreach ($dimensions as $key => $dimension) {
            if (! Schema::hasTable($dimension['table'])) {
                $result[$key] = [];

                continue;
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

            $result[$key] = $this->formatUnifiedAreaStats(
                $rows,
                $dimension['table'],
                $dimension['loai'],
            );
        }

        return $result;
    }

    /**
     * HTX: khớp text với danh mục hợp nhất, lấy cấp cha qua quan_huyen_id khi cần.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildCooperativeAreaBreakdowns(?User $user): array
    {
        $result = [
            'phuongXaCu' => [],
            'quanHuyenCu' => [],
            'phuongXaMoi' => [],
            'quanHuyenMoi' => [],
        ];

        if (! Schema::hasTable('hanh_chinh_phuong_xa')) {
            return $result;
        }

        $wardTextColumn = Schema::hasColumn('hop_tac_xas', 'phuong_xa') ? 'phuong_xa' : null;
        if ($wardTextColumn === null) {
            return $result;
        }

        $result['phuongXaCu'] = $this->formatUnifiedAreaStats(
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
        );

        $result['phuongXaMoi'] = $this->formatUnifiedAreaStats(
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
        );

        if (Schema::hasTable('hanh_chinh_quan_huyen')) {
            $result['quanHuyenCu'] = $this->formatUnifiedAreaStats(
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
            );

            $result['quanHuyenMoi'] = $this->formatUnifiedAreaStats(
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
            );
        }

        return $result;
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
