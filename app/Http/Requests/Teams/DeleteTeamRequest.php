<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteTeamRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'target_team_id' => [
                'nullable',
                'integer',
                Rule::exists('teams', 'id')
                    ->where('organization_id', $team->organization_id)
                    ->whereNot('id', $team->id),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'target_team_id.exists' => __('Choose another team from this organization, or leave it with the organization.'),
        ];
    }
}
