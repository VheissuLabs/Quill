<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

class OrganizationInvitationPolicy
{
    /**
     * Determine whether the user can invite people to the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel the invitation.
     */
    public function delete(User $user, OrganizationInvitation $invitation): bool
    {
        return $user->hasOrganizationPermission($invitation->organization, OrganizationPermission::CancelInvitation);
    }
}
