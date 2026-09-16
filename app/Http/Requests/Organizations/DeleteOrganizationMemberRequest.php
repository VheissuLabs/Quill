<?php

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteOrganizationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('current_organization');
        $member = $this->route('user');

        if (! $organization instanceof Organization || ! $member instanceof User) {
            return true;
        }

        $membership = $organization->memberships()->where('user_id', $member->id)->first();

        return $membership === null || Gate::allows('delete', $membership);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
