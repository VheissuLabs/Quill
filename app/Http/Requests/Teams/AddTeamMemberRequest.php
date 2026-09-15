<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddTeamMemberRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('organization_members', 'user_id')->where('organization_id', $team->organization_id),
                Rule::unique('team_members', 'user_id')->where('team_id', $team->id),
            ],
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
            'user_id.exists' => __('Only organization members can be added to a team.'),
            'user_id.unique' => __('This user is already a member of the team.'),
        ];
    }
}
