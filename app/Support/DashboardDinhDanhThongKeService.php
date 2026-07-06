<?php

namespace App\Support;

use App\Models\DnDinhDanhLichSu;
use App\Models\User;
use Carbon\Carbon;
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

        $logs = DnDinhDanhLichSu::query()
            ->select(['gia_tri_moi', 'created_at'])
            ->whereBetween('created_at', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->whereHas('doanhNghiep', function ($query) use ($user) {
                DoanhNghiepScopeHelper::applyScope($query, $user);
            })
            ->get();

        $countsByDate = [];
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $countsByDate[$day->toDateString()] = [
                'daDinhDanh' => 0,
                'chuaDinhDanh' => 0,
            ];
        }

        foreach ($logs as $log) {
            $dateKey = $log->created_at?->toDateString();

            if ($dateKey === null || !isset($countsByDate[$dateKey])) {
                continue;
            }

            if ($log->gia_tri_moi) {
                $countsByDate[$dateKey]['daDinhDanh']++;
            } else {
                $countsByDate[$dateKey]['chuaDinhDanh']++;
            }
        }

        $days = [];
        $totalDaDinhDanh = 0;
        $totalChuaDinhDanh = 0;

        foreach ($countsByDate as $date => $counts) {
            $totalDaDinhDanh += $counts['daDinhDanh'];
            $totalChuaDinhDanh += $counts['chuaDinhDanh'];

            $days[] = [
                'date' => $date,
                'label' => Carbon::parse($date)->format('d/m'),
                'daDinhDanh' => $counts['daDinhDanh'],
                'chuaDinhDanh' => $counts['chuaDinhDanh'],
            ];
        }

        return [
            'month' => $monthStart->format('Y-m'),
            'monthLabel' => 'Tháng ' . $monthStart->format('n/Y'),
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
