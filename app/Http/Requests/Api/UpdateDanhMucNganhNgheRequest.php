<?php

namespace App\Http\Requests\Api;

use App\Models\DanhMucNganhNghe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDanhMucNganhNgheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ten' => ['sometimes', 'string', 'max:255'],
            'thuTu' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->has('isActive')) {
                return;
            }

            $item = $this->route('danh_muc_nganh_nghe');
            if (!$item instanceof DanhMucNganhNghe) {
                return;
            }

            $activating = filter_var($this->input('isActive'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
            if ($activating && $item->parent_id) {
                $parent = DanhMucNganhNghe::query()->find($item->parent_id);
                if ($parent && !$parent->is_active) {
                    $validator->errors()->add('isActive', 'Không thể kích hoạt khi danh mục cha đang ngừng hoạt động.');
                }
            }
        });
    }
}
