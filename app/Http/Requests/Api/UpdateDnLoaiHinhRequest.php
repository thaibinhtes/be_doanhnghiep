<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDnLoaiHinhRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('dn_loai_hinh')?->id ?? $this->route('dn_loai_hinh');

        return [
            'ma' => ['sometimes', 'string', 'max:50', Rule::unique('dn_loai_hinhs', 'ma')->ignore($id)],
            'ten' => ['sometimes', 'string', 'max:255'],
            'thuTu' => ['nullable', 'integer', 'min:0'],
            'macDinh' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'moTa' => ['nullable', 'string'],
        ];
    }
}
