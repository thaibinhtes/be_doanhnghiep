<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoanhNghiepRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('doanhNghiep')?->id ?? $this->route('doanh_nghiep');

        return [
            'tt' => ['nullable', 'integer'],
            'maSoDoanhNghiep' => ['nullable', 'string', 'max:50', Rule::unique('doanh_nghieps', 'ma_so_doanh_nghiep')->ignore($id)],
            'tenDoanhNghiep' => ['sometimes', 'required', 'string', 'max:255'],
            'diaChi' => ['nullable', 'string'],
            'long' => ['nullable', 'numeric', 'between:-180,180'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'quanHuyen' => ['nullable', 'string', 'max:100'],
            'phuongXa' => ['nullable', 'string', 'max:100'],
            'vonDieuLe' => ['nullable', 'string', 'max:100'],
            'trangThai' => ['nullable', 'string', 'max:100'],
            'dnTrangThaiId' => ['nullable', 'integer', 'exists:dn_trang_thais,id'],
            'lyDoTrangThai' => ['nullable', 'string'],
            'daCapNhatDinhDanh' => ['nullable', 'boolean'],
            'dienThoai' => ['nullable', 'string', 'max:50'],
            'nguoiDaiDienTen' => ['nullable', 'string', 'max:255'],
            'ngaySinhNguoiDaiDien' => ['nullable', 'string', 'max:50'],
            'chuSoHuuTen' => ['nullable', 'string', 'max:255'],
            'nguoiDaiDien' => ['nullable', 'integer', 'exists:members,id'],
            'nguoiDaiDienID' => ['nullable', 'integer', 'exists:members,id'],
            'chuSoHuuID' => ['nullable', 'integer', 'exists:members,id'],
            'chuSoHuu' => ['nullable', 'integer', 'exists:members,id'],
            'nganhNgheKDChinh' => ['nullable', 'string', 'max:255'],
            'nganhNgheKD' => ['nullable', 'string'],
            'ngayCap' => ['nullable', 'string', 'max:50'],
            'ngayDangKyThayDoi' => ['nullable', 'string', 'max:50'],
            'loaiHinhDN' => ['nullable', 'string', 'max:100'],
            'soLuongLaoDong' => ['nullable', 'integer', 'min:0'],
            'loaiDN' => ['nullable', 'string', 'max:100'],
            'dsCoDong' => ['nullable', 'string'],
            'danhSachThanhVienGopVon' => ['nullable', 'array'],
            'danhSachThanhVienGopVon.*.fullName' => ['required', 'string', 'max:255'],
            'danhSachThanhVienGopVon.*.position' => ['nullable', 'string', 'max:255'],
            'danhSachThanhVienGopVon.*.investmentAmount' => ['nullable', 'numeric', 'min:0'],
            'danhSachThanhVienGopVon.*.dateJoin' => ['nullable', 'string', 'max:50'],
            'danhSachThanhVienGopVon.*.memberId' => ['nullable', 'integer', 'exists:members,id'],
        ];
    }
}
