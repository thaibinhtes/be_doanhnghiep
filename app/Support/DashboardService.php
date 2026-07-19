<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

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
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildCompanyAreaBreakdowns(?User $user): array
    {
        $dimensions = [
            'quanHuyenMoi' => ['tinh_thanh', 'tinh_thanh_code', 'quan_huyen'],
            'quanHuyenCu' => ['quan_huyen_cu', 'quan_huyen_cu_code', 'quan_huyen'],
            'phuongXaMoi' => ['xa_phuong', 'xa_phuong_code', 'phuong_xa'],
            'phuongXaCu' => ['xa_phuong_cu', 'xa_phuong_cu_code', 'phuong_xa'],
        ];

        $result = [];

        foreach ($dimensions as $key => [$areaTable, $foreignKey, $textColumn]) {
            $rows = DoanhNghiepScopeHelper::query($user)
                ->leftJoin("{$areaTable} as admin_area", function ($join) use ($foreignKey, $textColumn) {
                    $join->whereRaw(
                        '(admin_area.code = doanh_nghieps.' . $foreignKey . '
                            OR (
                                NULLIF(doanh_nghieps.' . $foreignKey . ", '') IS NULL
                                AND LOWER(TRIM(admin_area.full_name)) = LOWER(TRIM(doanh_nghieps." . $textColumn . '))
                            ))',
                    );
                })
                ->leftJoin(
                    'company_tax_managements as company_tax',
                    'company_tax.doanh_nghiep_id',
                    '=',
                    'doanh_nghieps.id',
                )
                ->selectRaw('admin_area.code as area_code, admin_area.full_name as area_name')
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
                ->groupBy('admin_area.code', 'admin_area.full_name')
                ->get();

            $result[$key] = $this->formatAreaStats($rows, $areaTable);
        }

        return $result;
    }

    /**
     * HTX hiện lưu tên phường/xã dạng text. Ghép tên này với danh mục hành chính
     * đã đồng bộ từ API để xác định đơn vị cũ/mới và cấp cha tương ứng.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildCooperativeAreaBreakdowns(?User $user): array
    {
        $result = [];

        $result['phuongXaCu'] = $this->formatAreaStats(
            $this->selectCooperativeAreaCounts(
                $this->buildCooperativeAreaQuery($user)
                    ->leftJoin('xa_phuong_cu as admin_area', function ($join) {
                        $join->on(
                            $this->normalizedSql('admin_area.full_name'),
                            '=',
                            $this->normalizedSql('hop_tac_xas.phuong_xa'),
                        );
                    }),
                'admin_area',
            )->get(),
            'xa_phuong_cu',
        );

        $result['quanHuyenCu'] = $this->formatAreaStats(
            $this->selectCooperativeAreaCounts(
                $this->buildCooperativeAreaQuery($user)
                    ->leftJoin('xa_phuong_cu as matched_ward', function ($join) {
                        $join->on(
                            $this->normalizedSql('matched_ward.full_name'),
                            '=',
                            $this->normalizedSql('hop_tac_xas.phuong_xa'),
                        );
                    })
                    ->leftJoin(
                        'quan_huyen_cu as admin_area',
                        'admin_area.code',
                        '=',
                        'matched_ward.quan_huyen_cu_code',
                    ),
                'admin_area',
            )->get(),
            'quan_huyen_cu',
        );

        $result['phuongXaMoi'] = $this->formatAreaStats(
            $this->selectCooperativeAreaCounts(
                $this->buildCooperativeAreaQuery($user)
                    ->leftJoin('xa_phuong as admin_area', function ($join) {
                        $join->on(
                            $this->normalizedSql('admin_area.full_name'),
                            '=',
                            $this->normalizedSql('hop_tac_xas.phuong_xa'),
                        );
                    }),
                'admin_area',
            )->get(),
            'xa_phuong',
        );

        $result['quanHuyenMoi'] = $this->formatAreaStats(
            $this->selectCooperativeAreaCounts(
                $this->buildCooperativeAreaQuery($user)
                    ->leftJoin('xa_phuong as matched_ward', function ($join) {
                        $join->on(
                            $this->normalizedSql('matched_ward.full_name'),
                            '=',
                            $this->normalizedSql('hop_tac_xas.phuong_xa'),
                        );
                    })
                    ->leftJoin(
                        'tinh_thanh as admin_area',
                        'admin_area.code',
                        '=',
                        'matched_ward.tinh_thanh_code',
                    ),
                'admin_area',
            )->get(),
            'tinh_thanh',
        );

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatAreaStats($rows, string $catalogTable): array
    {
        $countsByCode = $rows
            ->filter(fn ($row) => $row->area_code !== null)
            ->keyBy(fn ($row) => (string) $row->area_code);

        $catalogRows = DB::table($catalogTable)
            ->select(['code', 'full_name'])
            ->orderBy('full_name')
            ->get()
            ->map(function ($area) use ($countsByCode) {
                $stats = $countsByCode->get((string) $area->code);

                return [
                    'areaCode' => (string) $area->code,
                    'areaName' => (string) $area->full_name,
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
            ->selectRaw("{$areaAlias}.code as area_code, {$areaAlias}.full_name as area_name")
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
            ->groupBy("{$areaAlias}.code", "{$areaAlias}.full_name");
    }

    private function normalizedSql(string $column): Expression
    {
        return DB::raw("LOWER(TRIM({$column}))");
    }
}
