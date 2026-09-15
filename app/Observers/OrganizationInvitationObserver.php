<?php

namespace App\Observers;

use App\Models\OrganizationInvitation;
use Illuminate\Support\Str;

class OrganizationInvitationObserver
{
    /**
     * Give a new invitation its acceptance code.
     */
    public function creating(OrganizationInvitation $invitation): void
    {
        if (empty($invitation->code)) {
            $invitation->code = Str::random(64);
        }
    }
}
