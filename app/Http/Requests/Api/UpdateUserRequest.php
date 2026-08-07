<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'roleId' => ['nullable', 'integer', 'exists:roles,id'],
            'donViId' => ['nullable', 'integer', 'exists:don_vis,id'],
            'phongBanId' => ['nullable', 'integer', 'exists:phong_bans,id'],
            'chucDanh' => ['nullable', 'string', 'max:255'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
