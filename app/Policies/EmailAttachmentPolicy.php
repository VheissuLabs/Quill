<?php

namespace App\Policies;

use App\Models\EmailAttachment;
use App\Models\User;

class EmailAttachmentPolicy
{
    public function view(User $user, EmailAttachment $attachment): bool
    {
        $ticket = $attachment->ticket();

        return $ticket !== null && $user->can('view', $ticket);
    }
}
