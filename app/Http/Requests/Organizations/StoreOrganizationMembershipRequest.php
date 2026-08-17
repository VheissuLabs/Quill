<?php

namespace App\Http\Requests\Organizations;

use App\Concerns\PasswordValidationRules;
use App\Rules\ValidOrganizationInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationMembershipRequest extends FormRequest
{
    use PasswordValidationRules;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        if ($this->user() !== null) {
            return [
                'invitation' => ['required', new ValidOrganizationInvitation($this->user())],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
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
