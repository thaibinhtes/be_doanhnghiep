<?php

namespace App\Support;

use App\Models\DnDinhDanhLichSu;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DashboardDinhDanhThongKeService
{
    /**
     * Thống kê số lượt đăng ký / hủy định danh theo từng ngày trong tháng.
     *
     * @return array<string, mixed>
     */
    public function buildMonthlyByDay(?User $user = null, ?string $month = null): array
    {
        $monthStart = $this->resolveMonthStart($month);
        $monthEnd = $monthStart->copy()->endOfMonth();

        $scopedCompanyIds = DoanhNghiepScopeHelper::query($user)->select('doanh_nghieps.id');

        $aggregated = DnDinhDanhLichSu::query()
            ->selectRaw('DATE(created_at) as log_date')
            ->selectRaw('SUM(CASE WHEN gia_tri_moi = 1 THEN 1 ELSE 0 END) as da_dinh_danh')
            ->selectRaw('SUM(CASE WHEN gia_tri_moi = 0 OR gia_tri_moi IS NULL THEN 1 ELSE 0 END) as chua_dinh_danh')
            ->whereBetween('created_at', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->whereIn('doanh_nghiep_id', $scopedCompanyIds)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy(fn ($row) => (string) $row->log_date);

        $days = [];
        $totalDaDinhDanh = 0;
        $totalChuaDinhDanh = 0;

        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $dateKey = $day->toDateString();
            $row = $aggregated->get($dateKey);
            $da = (int) ($row->da_dinh_danh ?? 0);
            $chua = (int) ($row->chua_dinh_danh ?? 0);
            $totalDaDinhDanh += $da;
            $totalChuaDinhDanh += $chua;

            $days[] = [
                'date' => $dateKey,
                'label' => $day->format('d/m'),
                'daDinhDanh' => $da,
                'chuaDinhDanh' => $chua,
            ];
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'monthLabel' => 'Tháng '.$monthStart->format('n/Y'),
            'from' => $monthStart->toDateString(),
            'to' => $monthEnd->toDateString(),
            'totals' => [
                'daDinhDanh' => $totalDaDinhDanh,
                'chuaDinhDanh' => $totalChuaDinhDanh,
            ],
            'days' => $days,
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    private function resolveMonthStart(?string $month): Carbon
    {
        if ($month === null || trim($month) === '') {
            return now()->startOfMonth()->startOfDay();
        }

        try {
            return Carbon::createFromFormat('Y-m', trim($month))->startOfMonth()->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Tham số month phải có định dạng Y-m (ví dụ: 2026-07).');
        }
    }
}
