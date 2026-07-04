<?php

namespace App\Http\Requests\Api;

use App\Models\DonVi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDonViRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parentId' => ['nullable', 'integer', 'exists:don_vis,id'],
            'cap' => ['required', 'integer', 'min:1', 'max:10'],
            'ma' => ['required', 'string', 'max:50'],
            'ten' => ['required', 'string', 'max:255'],
            'moTa' => ['nullable', 'string'],
            'thuTu' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $cap = (int) $this->input('cap');
            $parentId = $this->input('parentId');

            if ($cap === 1 && $parentId) {
                $validator->errors()->add('parentId', 'Cấp 1 không có đơn vị cha.');
            }

            if ($cap > 1 && !$parentId) {
                $validator->errors()->add('parentId', 'Vui lòng chọn đơn vị cha.');
            }

            if ($parentId) {
                $parent = DonVi::query()->find($parentId);
                if ($parent && (int) $parent->cap !== $cap - 1) {
                    $validator->errors()->add('parentId', 'Đơn vị cha phải thuộc cấp ' . ($cap - 1) . '.');
                }
            }

            $exists = DonVi::query()
                ->where('parent_id', $parentId)
                ->where('ma', (string) $this->input('ma'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('ma', 'Mã đơn vị đã tồn tại trong cùng cấp cha.');
            }
        });
    }
}
