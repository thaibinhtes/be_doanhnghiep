<?php

namespace App\Support;

use App\Models\DonVi;
use App\Models\User;

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
        $companyAreas = $this->buildCompanyAreaStats($user);
        $cooperativeAreas = $this->buildCooperativeAreaStats($user);

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
            'companyAreas' => $companyAreas,
            'cooperativeAreas' => $cooperativeAreas,
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
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanyAreaStats(?User $user): array
    {
        $rows = DoanhNghiepScopeHelper::query($user)
            ->leftJoin(
                'company_tax_managements as company_tax',
                'company_tax.doanh_nghiep_id',
                '=',
                'doanh_nghieps.id',
            )
            ->select('doanh_nghieps.don_vi_id')
            ->selectRaw('COUNT(doanh_nghieps.id) as total')
            ->selectRaw('SUM(CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = true THEN 1 ELSE 0 END) as da_dinh_danh')
            ->selectRaw(
                'SUM(CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = false AND company_tax.id IS NOT NULL THEN 1 ELSE 0 END) as can_ra_soat',
            )
            ->selectRaw(
                'SUM(CASE WHEN doanh_nghieps.da_cap_nhat_dinh_danh = false AND company_tax.id IS NULL THEN 1 ELSE 0 END) as chua_dinh_danh',
            )
            ->groupBy('doanh_nghieps.don_vi_id')
            ->get();

        return $this->formatAreaStats($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCooperativeAreaStats(?User $user): array
    {
        $rows = HopTacXaScopeHelper::query($user)
            ->leftJoin(
                'cooperative_tax_managements as cooperative_tax',
                'cooperative_tax.hop_tac_xa_id',
                '=',
                'hop_tac_xas.id',
            )
            ->select('hop_tac_xas.don_vi_id')
            ->selectRaw('COUNT(hop_tac_xas.id) as total')
            ->selectRaw(
                'SUM(CASE WHEN cooperative_tax.id IS NOT NULL AND cooperative_tax.is_active = true THEN 1 ELSE 0 END) as da_dinh_danh',
            )
            ->selectRaw(
                'SUM(CASE WHEN cooperative_tax.id IS NOT NULL AND cooperative_tax.is_active = false THEN 1 ELSE 0 END) as can_ra_soat',
            )
            ->selectRaw(
                'SUM(CASE WHEN cooperative_tax.id IS NULL THEN 1 ELSE 0 END) as chua_dinh_danh',
            )
            ->groupBy('hop_tac_xas.don_vi_id')
            ->get();

        return $this->formatAreaStats($rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function formatAreaStats($rows): array
    {
        $unitNames = DonVi::query()
            ->whereIn('id', $rows->pluck('don_vi_id')->filter()->all())
            ->pluck('ten', 'id');

        return $rows
            ->map(function ($row) use ($unitNames) {
                $donViId = $row->don_vi_id !== null ? (int) $row->don_vi_id : null;

                return [
                    'donViId' => $donViId,
                    'donViTen' => $donViId !== null
                        ? (string) ($unitNames[$donViId] ?? "Đơn vị {$donViId}")
                        : 'Chưa phân địa bàn',
                    'total' => (int) $row->total,
                    'daDinhDanh' => (int) $row->da_dinh_danh,
                    'canRaSoat' => (int) $row->can_ra_soat,
                    'chuaDinhDanh' => (int) $row->chua_dinh_danh,
                ];
            })
            ->sortBy('donViTen', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
