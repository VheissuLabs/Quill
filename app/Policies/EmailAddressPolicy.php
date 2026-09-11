<?php

namespace App\Policies;

use App\Models\EmailAddress;
use App\Models\User;

class EmailAddressPolicy
{
    /**
     * Determine whether the user can verify or remove the email address.
     */
    public function manage(User $user, EmailAddress $emailAddress): bool
    {
        return $user->id === $emailAddress->user_id;
    }
}
