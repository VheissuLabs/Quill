<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

class OrganizationInvitationPolicy
{
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::CreateInvitation);
    }

    public function delete(User $user, OrganizationInvitation $invitation): bool
    {
        return $user->hasOrganizationPermission($invitation->organization, OrganizationPermission::CancelInvitation);
    }
}
