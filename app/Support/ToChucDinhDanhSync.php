<?php

namespace App\Support;

use App\Models\DoanhNghiep;
use App\Models\HopTacXa;
use App\Models\ToChucDinhDanh;
use Carbon\Carbon;

class ToChucDinhDanhSync
{
    public static function syncDoanhNghiep(DoanhNghiep $doanhNghiep, bool $daDinhDanh, ?Carbon $thoiGian = null): void
    {
        $maSo = trim((string) $doanhNghiep->ma_so_doanh_nghiep);
        if ($maSo === '') {
            return;
        }

        if (! $daDinhDanh) {
            ToChucDinhDanh::query()
                ->where('loai_to_chuc', ToChucDinhDanh::LOAI_DOANH_NGHIEP)
                ->where(function ($query) use ($doanhNghiep, $maSo) {
                    $query->where('doanh_nghiep_id', $doanhNghiep->id)
                        ->orWhere('ma_so', $maSo);
                })
                ->update([
                    'da_dinh_danh' => false,
                    'updated_at' => now(),
                ]);

            return;
        }

        $occurredAt = $thoiGian?->copy() ?? self::resolveOccurredAt();

        ToChucDinhDanh::query()->updateOrCreate(
            [
                'loai_to_chuc' => ToChucDinhDanh::LOAI_DOANH_NGHIEP,
                'ma_so' => $maSo,
            ],
            [
                'ten_to_chuc' => $doanhNghiep->ten_doanh_nghiep,
                'doanh_nghiep_id' => $doanhNghiep->id,
                'hop_tac_xa_id' => null,
                'thoi_gian_dinh_danh' => $occurredAt,
                'da_dinh_danh' => true,
                'user_id' => auth('api')->id(),
                'nguon' => (string) DinhDanhHistoryContext::get('nguon', 'he_thong'),
                'ghi_chu' => DinhDanhHistoryContext::get('ghi_chu'),
            ],
        );
    }

    public static function syncHopTacXa(HopTacXa $hopTacXa, bool $daDinhDanh, ?Carbon $thoiGian = null): void
    {
        $maSo = trim((string) $hopTacXa->ma_so_thue);
        if ($maSo === '') {
            return;
        }

        if (! $daDinhDanh) {
            ToChucDinhDanh::query()
                ->where('loai_to_chuc', ToChucDinhDanh::LOAI_HOP_TAC_XA)
                ->where(function ($query) use ($hopTacXa, $maSo) {
                    $query->where('hop_tac_xa_id', $hopTacXa->id)
                        ->orWhere('ma_so', $maSo);
                })
                ->update([
                    'da_dinh_danh' => false,
                    'updated_at' => now(),
                ]);

            return;
        }

        $occurredAt = $thoiGian?->copy() ?? self::resolveOccurredAt();

        ToChucDinhDanh::query()->updateOrCreate(
            [
                'loai_to_chuc' => ToChucDinhDanh::LOAI_HOP_TAC_XA,
                'ma_so' => $maSo,
            ],
            [
                'ten_to_chuc' => $hopTacXa->ten_htx,
                'doanh_nghiep_id' => null,
                'hop_tac_xa_id' => $hopTacXa->id,
                'thoi_gian_dinh_danh' => $occurredAt,
                'da_dinh_danh' => true,
                'user_id' => auth('api')->id(),
                'nguon' => (string) DinhDanhHistoryContext::get('nguon', 'he_thong'),
                'ghi_chu' => DinhDanhHistoryContext::get('ghi_chu'),
            ],
        );
    }

    private static function resolveOccurredAt(): Carbon
    {
        $thoiDiem = DinhDanhHistoryContext::get('thoi_diem');

        if ($thoiDiem instanceof Carbon) {
            return $thoiDiem->copy();
        }

        if (is_string($thoiDiem) && trim($thoiDiem) !== '') {
            try {
                return Carbon::parse($thoiDiem);
            } catch (\Throwable) {
                // fall through
            }
        }

        return now();
    }
}
