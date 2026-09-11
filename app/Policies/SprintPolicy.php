<?php

namespace App\Policies;

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
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can view the sprint.
     */
    public function view(User $user, Sprint $sprint): bool
    {
        return $user->belongsToOrganization($sprint->team->organization);
    }

    /**
     * Determine whether the user can create sprints in the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageSprints);
    }

    /**
     * Determine whether the user can update, start or complete the sprint.
     */
    public function update(User $user, Sprint $sprint): bool
    {
        return $user->hasOrganizationPermission($sprint->team->organization, OrganizationPermission::ManageSprints);
    }

    /**
     * Determine whether the user can delete the sprint.
     */
    public function delete(User $user, Sprint $sprint): bool
    {
        return $user->hasOrganizationPermission($sprint->team->organization, OrganizationPermission::ManageSprints);
    }
}
