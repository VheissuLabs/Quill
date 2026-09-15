<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteTeamRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Leaving the target out deletes the team outright; naming another team of the same
     * organization moves the team's work there first.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_team_id.exists' => __('Choose another team from this organization to move the work to.'),
        ];
    }
}
