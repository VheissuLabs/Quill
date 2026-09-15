<?php

namespace App\Policies;

use App\Models\EmailAddress;
use App\Models\User;

class EmailAddressPolicy
{
    public function manage(User $user, EmailAddress $emailAddress): bool
    {
        return $user->id === $emailAddress->user_id;
    }
}
