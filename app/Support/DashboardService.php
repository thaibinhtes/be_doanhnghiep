<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use App\Models\User;

class DashboardService
{
    public function __construct(
        private readonly BaoCaoTongHopService $baoCaoTongHopService,
        private readonly BaoCaoTienDoDinhDanhService $tienDoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(?User $user = null): array
    {
        $summary = $this->baoCaoTongHopService->build($user);
        $progress = $this->tienDoService->build([], $user);

        $companyQuery = DoanhNghiepScopeHelper::query($user);
        $totalCompanies = (clone $companyQuery)->count();
        $identified = (clone $companyQuery)->where('da_cap_nhat_dinh_danh', true)->count();
        $notIdentified = max(0, $totalCompanies - $identified);
        $withCoordinates = (clone $companyQuery)
            ->whereNotNull('long')
            ->whereNotNull('lat')
            ->count();

        $progressTotalRow = collect($progress['rows'] ?? [])
            ->firstWhere('key', 'tong_cong');

        return [
            'generatedAt' => now()->toIso8601String(),
            'overview' => [
                'totalCompanies' => $totalCompanies,
                'identified' => $identified,
                'notIdentified' => $notIdentified,
                'withCoordinates' => $withCoordinates,
            ],
            'identity' => [
                'daDinhDanh' => $identified,
                'chuaDinhDanh' => $notIdentified,
            ],
            'summary' => $summary,
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
