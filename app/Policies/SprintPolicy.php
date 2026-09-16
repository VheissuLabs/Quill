<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Sprint;
use App\Models\User;

class SprintPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Sprints)
            && $user->belongsToOrganization($organization);
    }

    public function view(User $user, Sprint $sprint): bool
    {
        return $sprint->organization()->canUse(OrganizationCapability::Sprints)
            && $user->belongsToOrganization($sprint->organization());
    }

    public function create(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Sprints)
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageSprints);
    }

    public function update(User $user, Sprint $sprint): bool
    {
        return $sprint->organization()->canUse(OrganizationCapability::Sprints)
            && $user->hasOrganizationPermission($sprint->organization(), OrganizationPermission::ManageSprints);
    }

    public function delete(User $user, Sprint $sprint): bool
    {
        return $sprint->organization()->canUse(OrganizationCapability::Sprints)
            && $user->hasOrganizationPermission($sprint->organization(), OrganizationPermission::ManageSprints);
    }
}
