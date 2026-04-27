<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberCompanyRequest extends FormRequest
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
        $id = $this->route('memberCompany')?->id;

        return [
            'memberId' => ['sometimes', 'required', 'integer', 'exists:members,id'],
            'doanhNghiepId' => [
                'sometimes',
                'required',
                'integer',
                'exists:doanh_nghieps,id',
                Rule::unique('member_companies', 'doanh_nghiep_id')
                    ->where('member_id', $this->input('memberId'))
                    ->ignore($id),
            ],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'doanhNghiepId.unique' => 'This member is already associated with this company.',
        ];
    }
}
