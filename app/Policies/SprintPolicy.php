<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Sprint;
use App\Models\User;

class SprintPolicy
{
    /**
     * Determine whether the user can view the organization's sprints.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Sprints)
            && $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can view the sprint.
     */
    public function view(User $user, Sprint $sprint): bool
    {
        return $sprint->organization()->canUse(OrganizationCapability::Sprints)
            && $user->belongsToOrganization($sprint->organization());
    }

    /**
     * Determine whether the user can create sprints in the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Sprints)
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageSprints);
    }

    /**
     * Determine whether the user can update, start or complete the sprint.
     */
    public function update(User $user, Sprint $sprint): bool
    {
        return $sprint->organization()->canUse(OrganizationCapability::Sprints)
            && $user->hasOrganizationPermission($sprint->organization(), OrganizationPermission::ManageSprints);
    }

    /**
     * Determine whether the user can delete the sprint.
     */
    public function delete(User $user, Sprint $sprint): bool
    {
        return $sprint->organization()->canUse(OrganizationCapability::Sprints)
            && $user->hasOrganizationPermission($sprint->organization(), OrganizationPermission::ManageSprints);
    }
}
