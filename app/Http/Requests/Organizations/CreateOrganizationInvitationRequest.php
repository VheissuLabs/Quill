<?php

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use App\Rules\UniqueOrganizationInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrganizationInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueOrganizationInvitation($organization)],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('organization_id', $organization->id)->where('is_owner', 0)],
        ];
    }
}
