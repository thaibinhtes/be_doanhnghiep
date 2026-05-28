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
            'daCapNhatDinhDanh' => (bool) $this->da_cap_nhat_dinh_danh,
            'trangThaiDinhDanh' => $this->da_cap_nhat_dinh_danh ? 'Đã cập nhật định danh' : 'Chưa cập nhật định danh',
            'dienThoai' => $this->dien_thoai,
            'nguoiDaiDienTen' => $this->nguoi_dai_dien_ten,
            'ngaySinhNguoiDaiDien' => $this->ngay_sinh_nguoi_dai_dien,
            'chuSoHuuTen' => $this->chu_so_huu_ten,
            'nguoiDaiDien' => $this->whenLoaded('nguoiDaiDien', fn () => new MemberResource($this->nguoiDaiDien)),
            'chuSoHuu' => $this->whenLoaded('chuSoHuu', fn () => new MemberResource($this->chuSoHuu)),
            'nganhNgheKDChinh' => $this->nganh_nghe_kd_chinh,
            'nganhNgheKD' => $this->nganh_nghe_kd,
            'ngayCap' => $this->ngay_cap,
            'ngayDangKyThayDoi' => $this->ngay_dang_ky_thay_doi,
            'loaiHinhDN' => $this->loai_hinh_dn,
            'soLuongLaoDong' => $this->so_luong_lao_dong,
            'loaiDN' => $this->loai_dn,
            'dsCoDong' => $this->ds_co_dong,
            'dsThanhVienGopVon' => $this->whenLoaded('memberCompanies', fn () =>
                $this->memberCompanies->map(fn ($mc) => [
                    'id' => $mc->id,
                    'memberId' => $mc->member_id,
                    'fullName' => $mc->member?->full_name ?? '',
                    'dateJoin' => $mc->date_join,
                    'position' => $mc->position,
                    'investmentAmount' => $mc->investment_amount,
                ])
            ),
            'danhSachThanhVienGopVon' => $this->whenLoaded('memberCompanies', fn () =>
                $this->memberCompanies->map(fn ($mc) => [
                    'id' => $mc->id,
                    'memberId' => $mc->member_id,
                    'fullName' => $mc->member?->full_name ?? '',
                    'dateJoin' => $mc->date_join,
                    'position' => $mc->position,
                    'investmentAmount' => $mc->investment_amount,
                ])
            ),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
