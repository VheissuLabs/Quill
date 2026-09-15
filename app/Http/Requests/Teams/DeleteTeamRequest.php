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
     * The team's work always goes somewhere: to another team of the same organization when one
     * is named, and back to the organization itself when none is.
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
            'target_team_id.exists' => __('Choose another team from this organization, or leave it with the organization.'),
        ];
    }
}
