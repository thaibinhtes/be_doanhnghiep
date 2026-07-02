<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDnTrangThaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('dn_trang_thai')?->id ?? $this->route('dn_trang_thai');

        return [
            'ma' => ['sometimes', 'string', 'max:50', Rule::unique('dn_trang_thais', 'ma')->ignore($id)],
            'ten' => ['sometimes', 'string', 'max:255'],
            'loai' => ['sometimes', Rule::in(['dinh_danh', 'hoat_dong', 'bao_cao'])],
            'yeuCauLyDo' => ['nullable', 'boolean'],
            'hienThiBaoCao' => ['nullable', 'boolean'],
            'thuTuBaoCao' => ['nullable', 'integer', 'min:1'],
            'macDinh' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'moTa' => ['nullable', 'string'],
        ];
    }
}
