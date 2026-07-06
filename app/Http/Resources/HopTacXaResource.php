<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\HopTacXa */
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
            'phuongXa' => $this->phuong_xa,
            'dienTichHa' => $this->dien_tich_ha !== null ? (float) $this->dien_tich_ha : null,
            'vonDieuLe' => $this->von_dieu_le,
            'soThanhVien' => $this->so_thanh_vien,
            'soNguoiLaoDong' => $this->so_nguoi_lao_dong,
            'linhVuc' => $this->linh_vuc,
            'hoatDong' => $this->hoat_dong,
            'dsThanhVien' => $this->ds_thanh_vien,
            'diaChiMoi' => $this->dia_chi_moi,
            'ghiChu' => $this->ghi_chu,
            'donViId' => $this->don_vi_id,
            'donViTen' => $this->when(
                $this->relationLoaded('donVi'),
                fn () => $this->donVi?->ten
            ),
            'donVi' => $this->whenLoaded('donVi', fn () => new DonViResource($this->donVi)),
            'createdByUserId' => $this->created_by_user_id,
            'createdByUser' => $this->whenLoaded('createdByUser', fn () => new UserResource($this->createdByUser)),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
