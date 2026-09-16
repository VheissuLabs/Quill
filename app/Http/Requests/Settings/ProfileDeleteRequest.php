<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $stranded = $this->user()->organizations()
                    ->get()
                    ->filter(fn (Organization $organization) => $organization->isOwner($this->user()) && ! $organization->hasOtherOwner($this->user()))
                    ->pluck('name');

                if ($stranded->isNotEmpty()) {
                    $validator->errors()->add('password', __('You are the only owner of :organizations. Make someone else an owner, or delete the organization, first.', [
                        'organizations' => $stranded->join(', ', ' and '),
                    ]));
                }
            },
        ];
    }
}
