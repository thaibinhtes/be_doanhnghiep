<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $memberId = $this->route('member')?->id;

        return [
            'cccd' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('members', 'cccd')->ignore($memberId)],
            'fullName' => ['sometimes', 'required', 'string', 'max:255'],
            'birthday' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'dateJoin' => ['nullable', 'string', 'max:50'],
            'status' => ['boolean'],
            'position' => ['nullable', 'string', 'max:255'],
            'investmentAmount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
