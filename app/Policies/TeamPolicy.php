<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view the organization's teams.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToOrganization($team->organization);
    }

    /**
     * Determine whether the user can create teams in the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageTeams);
    }

    /**
     * Determine whether the user can update the team, including its members.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasOrganizationPermission($team->organization, OrganizationPermission::ManageTeams);
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->hasOrganizationPermission($team->organization, OrganizationPermission::ManageTeams);
    }
}
