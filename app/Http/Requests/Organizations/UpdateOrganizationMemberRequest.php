<?php

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('current_organization');
        $member = $this->route('user');

        if (! $organization instanceof Organization || ! $member instanceof User) {
            return true;
        }

        $membership = $organization->memberships()->where('user_id', $member->id)->first();
        $role = Role::find($this->input('role_id'));

        return $membership === null || $role === null || Gate::allows('update', [$membership, $role]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('organization_id', $organization->id)],
        ];
    }
}
