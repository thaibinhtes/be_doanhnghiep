<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class BaoCaoTongHopHtxService
{
    /**
     * @return array{stt: int, columns: array<int, array{ma: string, ten: string, count: int}>, generatedAt: string}
     */
    public function build(?User $user = null): array
    {
        $grouped = HopTacXaScopeHelper::query($user)
            ->toBase()
            ->selectRaw(
                "CASE
                    WHEN NULLIF(TRIM(COALESCE(hoat_dong, '')), '') IS NULL THEN 'Chưa xác định'
                    ELSE TRIM(hoat_dong)
                END as ten",
            )
            ->selectRaw('COUNT(*) as total')
            ->groupBy('ten')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $label = (string) $row->ten;

                return [
                    'ma' => Str::slug($label, '_'),
                    'ten' => $label,
                    'count' => (int) $row->total,
                ];
            })
            ->values()
            ->all();

        return [
            'stt' => 1,
            'columns' => $grouped,
            'generatedAt' => now()->toIso8601String(),
        ];
    }
}
