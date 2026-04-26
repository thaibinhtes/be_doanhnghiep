<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoanhNghiepResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tt' => $this->tt,
            'maSoDoanhNghiep' => $this->ma_so_doanh_nghiep,
            'tenDoanhNghiep' => $this->ten_doanh_nghiep,
            'diaChi' => $this->dia_chi,
            'quanHuyen' => $this->quan_huyen,
            'phuongXa' => $this->phuong_xa,
            'vonDieuLe' => $this->von_dieu_le,
            'trangThai' => $this->trang_thai,
            'dienThoai' => $this->dien_thoai,
            'nguoiDaiDien' => $this->whenLoaded('nguoiDaiDien', fn () => new MemberResource($this->nguoiDaiDien)),
            'chuSoHuu' => $this->whenLoaded('chuSoHuu', fn () => new MemberResource($this->chuSoHuu)),
            'nganhNgheKDChinh' => $this->nganh_nghe_kd_chinh,
            'nganhNgheKD' => $this->nganh_nghe_kd,
            'ngayCap' => $this->ngay_cap,
            'ngayDangKyThayDoi' => $this->ngay_dang_ky_thay_doi,
            'loaiHinhDN' => $this->loai_hinh_dn,
            'soLuongLaoDong' => $this->so_luong_lao_dong,
            'dsThanhVienGopVon' => $this->ds_thanh_vien_gop_von,
            'dsCoDong' => $this->ds_co_dong,
            'loaiDN' => $this->loai_dn,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'members' => $this->whenLoaded('members', fn () => MemberResource::collection($this->members)),
        ];
    }
}
