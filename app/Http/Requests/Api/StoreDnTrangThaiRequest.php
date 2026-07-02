<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDnTrangThaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ma' => ['required', 'string', 'max:50', 'unique:dn_trang_thais,ma'],
            'ten' => ['required', 'string', 'max:255'],
            'loai' => ['required', Rule::in(['dinh_danh', 'hoat_dong', 'bao_cao'])],
            'yeuCauLyDo' => ['nullable', 'boolean'],
            'hienThiBaoCao' => ['nullable', 'boolean'],
            'thuTuBaoCao' => ['nullable', 'integer', 'min:1'],
            'macDinh' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'moTa' => ['nullable', 'string'],
        ];
    }
}
