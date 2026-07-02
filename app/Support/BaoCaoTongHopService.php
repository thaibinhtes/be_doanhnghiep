<?php

namespace App\Support;

use App\Models\DnTrangThai;
use App\Models\DoanhNghiep;
use Illuminate\Support\Collection;

class BaoCaoTongHopService
{
    /**
     * @return array{stt: int, columns: array<int, array{ma: string, ten: string, count: int}>, generatedAt: string}
     */
    public function build(): array
    {
        $reportStatuses = DnTrangThai::query()
            ->where('hien_thi_bao_cao', true)
            ->where('is_active', true)
            ->orderBy('thu_tu_bao_cao')
            ->get();

        $countsByMa = DoanhNghiep::query()
            ->join('dn_trang_thais', 'doanh_nghieps.dn_trang_thai_id', '=', 'dn_trang_thais.id')
            ->selectRaw('dn_trang_thais.ma, COUNT(*) as total')
            ->groupBy('dn_trang_thais.ma')
            ->pluck('total', 'ma');

        $columns = $reportStatuses->map(function (DnTrangThai $status) use ($countsByMa) {
            $count = match ($status->ma) {
                'dang_hoat_dong' => $this->countDangHoatDong(),
                default => (int) ($countsByMa[$status->ma] ?? 0),
            };

            return [
                'ma' => $status->ma,
                'ten' => $status->ten,
                'count' => $count,
            ];
        })->values()->all();

        return [
            'stt' => 1,
            'columns' => $columns,
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    private function countDangHoatDong(): int
    {
        return DoanhNghiep::query()
            ->whereHas('dnTrangThai', function ($query) {
                $query->whereIn('loai', ['dinh_danh', 'hoat_dong']);
            })
            ->count();
    }

    public function reportStatuses(): Collection
    {
        return DnTrangThai::query()
            ->where('hien_thi_bao_cao', true)
            ->where('is_active', true)
            ->orderBy('thu_tu_bao_cao')
            ->get();
    }
}
