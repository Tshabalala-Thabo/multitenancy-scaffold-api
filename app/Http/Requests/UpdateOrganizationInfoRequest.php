<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateOrganizationInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $tenant = $this->route('tenant');

        $hasTenantPermission = $user->roles
            ->where('pivot.tenant_id', $tenant->id)
            ->flatMap(fn($role) => $role->permissions)
            ->contains(fn($perm) => $perm->name === 'settings:manage');
        return $hasTenantPermission;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $tenantId = $this->route('tenant') ? $this->route('tenant')->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')->ignore($tenantId)
            ],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'address' => ['required', 'array'],
            'address.street_address' => ['required', 'string', 'max:255'],
            'address.suburb' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.province' => ['required', 'string', 'max:255'],
            'address.postal_code' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The organization name is required.',
            'domain.required' => 'The domain is required.',
            'domain.unique' => 'This domain is already in use by another organization.',
            'logo.image' => 'The logo must be a valid image file.',
            'logo.max' => 'The logo must not be larger than 2MB.',
            'address.required' => 'The address information is required.',
            'address.street_address.required' => 'The street address is required.',
            'address.suburb.required' => 'The suburb is required.',
            'address.city.required' => 'The city is required.',
            'address.province.required' => 'The province is required.',
            'address.postal_code.required' => 'The postal code is required.',
        ];
    }
}
