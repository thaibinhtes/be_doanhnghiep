<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoanhNghiepRequest extends FormRequest
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
        return [
            'tt' => ['nullable', 'integer'],
            'maSoDoanhNghiep' => ['nullable', 'string', 'max:50', 'unique:doanh_nghieps,ma_so_doanh_nghiep'],
            'tenDoanhNghiep' => ['required', 'string', 'max:255'],
            'diaChi' => ['nullable', 'string'],
            'quanHuyen' => ['nullable', 'string', 'max:100'],
            'phuongXa' => ['nullable', 'string', 'max:100'],
            'vonDieuLe' => ['nullable', 'string', 'max:100'],
            'trangThai' => ['nullable', 'string', 'max:100'],
            'dienThoai' => ['nullable', 'string', 'max:50'],
            'nguoiDaiDien' => ['nullable', 'integer', 'exists:members,id'],
            'nguoiDaiDienID' => ['nullable', 'integer', 'exists:members,id'],
            'chuSoHuu' => ['nullable', 'integer', 'exists:members,id'],
            'chuSoHuuID' => ['nullable', 'integer', 'exists:members,id'],
            'nganhNgheKDChinh' => ['nullable', 'string', 'max:255'],
            'nganhNgheKD' => ['nullable', 'string'],
            'ngayCap' => ['nullable', 'string', 'max:50'],
            'ngayDangKyThayDoi' => ['nullable', 'string', 'max:50'],
            'loaiHinhDN' => ['nullable', 'string', 'max:100'],
            'soLuongLaoDong' => ['nullable', 'integer', 'min:0'],
            'loaiDN' => ['nullable', 'string', 'max:100'],
            'danhSachThanhVienGopVon' => ['nullable', 'array'],
            'danhSachThanhVienGopVon.*' => ['integer', 'exists:members,id'],
        ];
    }
}
