<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DnDinhDanhLichSuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doanhNghiepId' => $this->doanh_nghiep_id,
            'userId' => $this->user_id,
            'userName' => $this->whenLoaded('user', fn () => $this->user?->name),
            'maSoDoanhNghiep' => $this->ma_so_doanh_nghiep,
            'tenDoanhNghiep' => $this->ten_doanh_nghiep,
            'hanhDong' => $this->hanh_dong,
            'hanhDongLabel' => $this->hanh_dong === 'dang_ky' ? 'Đăng ký định danh' : 'Huỷ đăng ký định danh',
            'giaTriCu' => (bool) $this->gia_tri_cu,
            'giaTriMoi' => (bool) $this->gia_tri_moi,
            'nguon' => $this->nguon,
            'nguonLabel' => $this->nguonLabel(),
            'ghiChu' => $this->ghi_chu,
            'createdAt' => $this->created_at?->toIso8601String(),
            'donViTen' => $this->when(
                $this->relationLoaded('doanhNghiep') && $this->doanhNghiep?->relationLoaded('donVi'),
                fn () => $this->doanhNghiep?->donVi?->ten
            ),
            'donViMa' => $this->when(
                $this->relationLoaded('doanhNghiep') && $this->doanhNghiep?->relationLoaded('donVi'),
                fn () => $this->doanhNghiep?->donVi?->ma
            ),
        ];
    }

    private function nguonLabel(): string
    {
        return match ($this->nguon) {
            'thu_cong' => 'Thao tác thủ công',
            'hang_loat' => 'Cập nhật hàng loạt',
            'import' => 'Import Excel',
            'tao_moi' => 'Tạo doanh nghiệp',
            'cap_nhat' => 'Cập nhật doanh nghiệp',
            default => 'Hệ thống',
        };
    }
}
