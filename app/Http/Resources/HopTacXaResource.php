<?php

namespace App\Http\Resources;

use App\Models\HopTacXa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HopTacXa */
class HopTacXaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tt' => $this->tt,
            'tenHtx' => $this->ten_htx,
            'maSoThue' => $this->ma_so_thue,
            'namThanhLap' => $this->nam_thanh_lap,
            'chuTichHdqtTen' => $this->chu_tich_hdqt_ten,
            'dienThoai' => $this->dien_thoai,
            'diaChi' => $this->dia_chi,
            'diaChiCu' => $this->dia_chi_cu ?? $this->dia_chi,
            'diaChiMoi' => $this->dia_chi_moi,
            'phuongXa' => $this->phuong_xa,
            'phuongXaCu' => $this->xa_phuong_cu ?? $this->phuong_xa,
            'phuongXaMoi' => $this->xa_phuong_moi,
            'quanHuyenCu' => $this->quan_huyen_cu,
            'quanHuyenMoi' => $this->quan_huyen_moi,
            'tinhThanhCu' => $this->tinh_thanh_cu,
            'tinhThanhMoi' => $this->tinh_thanh_moi,
            'dienTichHa' => $this->dien_tich_ha !== null ? (float) $this->dien_tich_ha : null,
            'vonDieuLe' => $this->von_dieu_le,
            'soThanhVien' => $this->so_thanh_vien,
            'soNguoiLaoDong' => $this->so_nguoi_lao_dong,
            'linhVuc' => $this->linh_vuc,
            'hoatDong' => $this->hoat_dong,
            'dsThanhVien' => $this->ds_thanh_vien,
            'ghiChu' => $this->ghi_chu,
            'daCapNhatDinhDanh' => (bool) $this->da_cap_nhat_dinh_danh,
            'trangThaiDinhDanh' => $this->da_cap_nhat_dinh_danh ? 'Đã cập nhật định danh' : 'Chưa cập nhật định danh',
            'thoiGianDinhDanh' => $this->when(
                $this->relationLoaded('dinhDanh'),
                fn () => $this->dinhDanh?->thoi_gian_dinh_danh?->toIso8601String(),
            ),
            'donViId' => $this->don_vi_id,
            'donViTen' => $this->when(
                $this->relationLoaded('donVi'),
                fn () => $this->donVi?->ten
            ),
            'donVi' => $this->whenLoaded('donVi', fn () => new DonViResource($this->donVi)),
            'tinhTrangThue' => $this->when(
                $this->relationLoaded('taxManagement'),
                fn () => $this->taxManagement?->is_active
                    ? 'Đang hoạt động'
                    : ($this->taxManagement !== null ? 'Ngừng hoạt động' : 'Không hoạt động'),
            ),
            'hasTaxLink' => $this->when(
                $this->relationLoaded('taxManagement'),
                fn () => $this->taxManagement !== null && (bool) $this->taxManagement->is_active,
            ),
            'createdByUserId' => $this->created_by_user_id,
            'createdByUser' => $this->whenLoaded('createdByUser', fn () => new UserResource($this->createdByUser)),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
