<?php

namespace App\Policies;

use App\Enums\OrganizationModule;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->isModuleEnabled(OrganizationModule::Teams)
            && $user->belongsToOrganization($organization);
    }

    public function view(User $user, Team $team): bool
    {
        return $user->belongsToOrganization($team->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $organization->isModuleEnabled(OrganizationModule::Teams)
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageTeams);
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasOrganizationPermission($team->organization, OrganizationPermission::ManageTeams);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasOrganizationPermission($team->organization, OrganizationPermission::ManageTeams);
    }
}
