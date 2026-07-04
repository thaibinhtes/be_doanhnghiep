<?php

namespace App\Http\Requests\Api;

use App\Models\DonVi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDonViRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ten' => ['sometimes', 'string', 'max:255'],
            'moTa' => ['nullable', 'string'],
            'thuTu' => ['nullable', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->has('isActive') || $this->boolean('isActive')) {
                return;
            }

            /** @var DonVi|null $donVi */
            $donVi = $this->route('don_vi');
            if (!$donVi?->parent_id) {
                return;
            }

            $parent = DonVi::query()->find($donVi->parent_id);
            if ($parent && !$parent->is_active) {
                $validator->errors()->add('isActive', 'Không thể kích hoạt đơn vị con khi đơn vị cha đang ngừng hoạt động.');
            }
        });
    }
}
