<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Sprint;
use App\Models\Team;
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
        return $this->reachable($user, $sprint);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Sprints)
            && $user->hasOrganizationPermission($organization, OrganizationPermission::ManageSprints);
    }

    public function update(User $user, Sprint $sprint): bool
    {
        return $this->reachable($user, $sprint)
            && $user->hasOrganizationPermission($sprint->organization(), OrganizationPermission::ManageSprints);
    }

    public function delete(User $user, Sprint $sprint): bool
    {
        return $this->reachable($user, $sprint)
            && $user->hasOrganizationPermission($sprint->organization(), OrganizationPermission::ManageSprints);
    }

    private function reachable(User $user, Sprint $sprint): bool
    {
        $organization = $sprint->organization();
        $owner = $sprint->owner;

        if (! $sprint->runsSprints()) {
            return false;
        }

        if ($owner instanceof Team && ! $user->belongsToTeam($owner)) {
            return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageSprints);
        }

        return $user->belongsToOrganization($organization);
    }
}
