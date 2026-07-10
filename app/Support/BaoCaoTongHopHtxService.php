<?php

namespace App\Support;

use App\Models\HopTacXa;
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
            ->get(['hoat_dong'])
            ->groupBy(function (HopTacXa $item) {
                $label = trim((string) ($item->hoat_dong ?? ''));

                return $label !== '' ? $label : 'Chưa xác định';
            })
            ->map(function ($items, string $label) {
                return [
                    'ma' => Str::slug($label, '_'),
                    'ten' => $label,
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'stt' => 1,
            'columns' => $grouped,
            'generatedAt' => now()->toIso8601String(),
        ];
    }
}
