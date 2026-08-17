<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use App\Rules\UniqueTeamInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateTeamInvitationRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueTeamInvitation($team)],
        ];
    }
}
