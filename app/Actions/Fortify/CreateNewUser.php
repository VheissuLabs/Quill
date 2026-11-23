<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /** @param  array<string, string>  $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $invitation = OrganizationInvitation::query()
            ->open()
            ->where('code', (string) ($input['invitation'] ?? ''))
            ->first();

        if ($invitation && strcasecmp($invitation->email, $user->email) === 0) {
            $user->forceFill(['email_verified_at' => now()])->save();
            $invitation->acceptFor($user);
        }

        return $user;
    }
}
