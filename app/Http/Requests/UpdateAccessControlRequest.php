<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessControlRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'privacy_setting' => ['required', 'string', Rule::in(['public', 'private'])],
            'two_factor_auth_required' => ['required', 'boolean'],
            'password_policy' => ['required', 'array'],
            'password_policy.min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'password_policy.requires_uppercase' => ['required', 'boolean'],
            'password_policy.requires_lowercase' => ['required', 'boolean'],
            'password_policy.requires_number' => ['required', 'boolean'],
            'password_policy.requires_symbol' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'privacy_setting.in' => 'The selected privacy setting is invalid. Must be one of: public, private, or restricted.',
            'password_policy.min_length.min' => 'Password minimum length must be at least :min characters.',
            'password_policy.min_length.max' => 'Password minimum length may not be greater than :max characters.',
        ];
    }
}
