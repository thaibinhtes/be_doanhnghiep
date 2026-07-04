<?php

namespace App\Http\Requests\Api;

use App\Models\DanhMucNganhNghe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDanhMucNganhNgheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parentId' => ['nullable', 'integer', 'exists:danh_muc_nganh_nghes,id'],
            'cap' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'ma' => ['required', 'string', 'max:20'],
            'ten' => ['required', 'string', 'max:255'],
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
                $validator->errors()->add('parentId', 'Cấp 1 không có danh mục cha.');
            }

            if ($cap > 1 && !$parentId) {
                $validator->errors()->add('parentId', 'Vui lòng chọn danh mục cha.');
            }

            if ($parentId) {
                $parent = DanhMucNganhNghe::query()->find($parentId);
                if ($parent && (int) $parent->cap !== $cap - 1) {
                    $validator->errors()->add('parentId', 'Danh mục cha phải thuộc cấp ' . ($cap - 1) . '.');
                }
            }

            $exists = DanhMucNganhNghe::query()
                ->where('parent_id', $parentId)
                ->where('ma', (string) $this->input('ma'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('ma', 'Mã ngành đã tồn tại trong cùng cấp cha.');
            }
        });
    }
}
