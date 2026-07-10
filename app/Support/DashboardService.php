<?php

namespace App\Support;

use App\Models\DoanhNghiep;
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
}
