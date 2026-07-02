<?php

namespace App\Support;

use App\Models\DnTrangThai;
use App\Models\DoanhNghiep;
use Illuminate\Validation\ValidationException;

class DoanhNghiepStatusHelper
{
    public static function defaultStatusId(): int
    {
        $status = DnTrangThai::query()
            ->where('mac_dinh', true)
            ->where('is_active', true)
            ->first();

        if (!$status) {
            $status = DnTrangThai::query()->where('ma', 'chua_dinh_danh')->firstOrFail();
        }

        return $status->id;
    }

    /**
     * @param  array<string, mixed>  $data  snake_case keys
     * @return array<string, mixed>
     */
    public static function applyStatus(array $data, ?DoanhNghiep $existing = null): array
    {
        if (!array_key_exists('dn_trang_thai_id', $data) || $data['dn_trang_thai_id'] === null) {
            if ($existing?->dn_trang_thai_id) {
                unset($data['dn_trang_thai_id']);
            } else {
                $data['dn_trang_thai_id'] = self::defaultStatusId();
            }
        }

        if (!isset($data['dn_trang_thai_id'])) {
            return $data;
        }

        $status = DnTrangThai::query()->find($data['dn_trang_thai_id']);
        if (!$status || !$status->is_active) {
            throw ValidationException::withMessages([
                'dnTrangThaiId' => ['Trạng thái doanh nghiệp không hợp lệ.'],
            ]);
        }

        if ($status->yeu_cau_ly_do && empty(trim((string) ($data['ly_do_trang_thai'] ?? '')))) {
            throw ValidationException::withMessages([
                'lyDoTrangThai' => ['Vui lòng nhập lý do khi chọn trạng thái này.'],
            ]);
        }

        if (!$status->yeu_cau_ly_do) {
            $data['ly_do_trang_thai'] = null;
        }

        if ($status->loai === 'dinh_danh') {
            $data['da_cap_nhat_dinh_danh'] = $status->ma === 'da_dinh_danh';
        }

        $data['trang_thai'] = $status->ten;

        return $data;
    }

    public static function syncDinhDanhStatus(DoanhNghiep $doanhNghiep, bool $daCapNhatDinhDanh): void
    {
        $ma = $daCapNhatDinhDanh ? 'da_dinh_danh' : 'chua_dinh_danh';
        $status = DnTrangThai::query()->where('ma', $ma)->first();

        if ($status) {
            $doanhNghiep->update([
                'dn_trang_thai_id' => $status->id,
                'da_cap_nhat_dinh_danh' => $daCapNhatDinhDanh,
                'trang_thai' => $status->ten,
                'ly_do_trang_thai' => null,
            ]);
        } else {
            $doanhNghiep->update(['da_cap_nhat_dinh_danh' => $daCapNhatDinhDanh]);
        }
    }
}
