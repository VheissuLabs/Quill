<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SaveOrganizationRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $role = $this->route('role');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('organization_id', $organization->id)
                    ->ignore($role instanceof Role ? $role->id : null),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(OrganizationPermission::class)],
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
            'name.unique' => __('This organization already has a role with that name.'),
        ];
    }
}
