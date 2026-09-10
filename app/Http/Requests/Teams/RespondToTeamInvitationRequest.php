<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use App\Rules\ValidTeamInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RespondToTeamInvitationRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('team');

        if ($team instanceof Team && $this->user()?->belongsToTeam($team)) {
            return [];
        }

        return [
            'invitation' => ['required', new ValidTeamInvitation($this->user())],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'invitation' => $this->route('invitation'),
        ]);
    }
}
