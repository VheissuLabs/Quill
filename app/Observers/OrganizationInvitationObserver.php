<?php

namespace App\Observers;

use App\Models\OrganizationInvitation;
use Illuminate\Support\Str;

class OrganizationInvitationObserver
{
    public function creating(OrganizationInvitation $invitation): void
    {
        $invitation->code = Str::random(64);
    }
}
