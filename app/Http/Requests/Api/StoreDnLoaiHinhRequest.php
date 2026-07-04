<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDnLoaiHinhRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ma' => ['required', 'string', 'max:50', 'unique:dn_loai_hinhs,ma'],
            'ten' => ['required', 'string', 'max:255'],
            'thuTu' => ['nullable', 'integer', 'min:0'],
            'macDinh' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'moTa' => ['nullable', 'string'],
        ];
    }
}
