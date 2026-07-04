<?php

namespace App\Observers;

use App\Models\DnDinhDanhLichSu;
use App\Models\DoanhNghiep;
use App\Support\DinhDanhHistoryContext;

class DoanhNghiepObserver
{
    public function created(DoanhNghiep $doanhNghiep): void
    {
        if (!$doanhNghiep->da_cap_nhat_dinh_danh) {
            return;
        }

        $this->record($doanhNghiep, false, true);
    }

    public function updated(DoanhNghiep $doanhNghiep): void
    {
        if (!$doanhNghiep->wasChanged('da_cap_nhat_dinh_danh')) {
            return;
        }

        $this->record(
            $doanhNghiep,
            (bool) $doanhNghiep->getOriginal('da_cap_nhat_dinh_danh'),
            (bool) $doanhNghiep->da_cap_nhat_dinh_danh,
        );
    }

    private function record(DoanhNghiep $doanhNghiep, bool $oldValue, bool $newValue): void
    {
        if ($oldValue === $newValue) {
            return;
        }

        DnDinhDanhLichSu::query()->create([
            'doanh_nghiep_id' => $doanhNghiep->id,
            'user_id' => auth('api')->id(),
            'ma_so_doanh_nghiep' => $doanhNghiep->ma_so_doanh_nghiep,
            'ten_doanh_nghiep' => $doanhNghiep->ten_doanh_nghiep,
            'hanh_dong' => $newValue ? 'dang_ky' : 'huy_dang_ky',
            'gia_tri_cu' => $oldValue,
            'gia_tri_moi' => $newValue,
            'nguon' => (string) DinhDanhHistoryContext::get('nguon', 'he_thong'),
            'ghi_chu' => DinhDanhHistoryContext::get('ghi_chu'),
        ]);
    }
}
