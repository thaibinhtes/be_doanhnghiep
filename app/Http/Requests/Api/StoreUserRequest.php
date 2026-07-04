<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'roleId' => ['nullable', 'integer', 'exists:roles,id'],
            'donViId' => ['nullable', 'integer', 'exists:don_vis,id'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
