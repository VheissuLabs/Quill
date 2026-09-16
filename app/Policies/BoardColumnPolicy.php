<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\BoardColumn;
use App\Models\Team;
use App\Models\User;

class BoardColumnPolicy
{
    public function create(User $user, Team $team): bool
    {
        return $team->organization->canUse(OrganizationCapability::Workflow)
            && $user->hasOrganizationPermission($team->organization, OrganizationPermission::ManageTeams);
    }

    public function update(User $user, BoardColumn $column): bool
    {
        return $column->organization()->canUse(OrganizationCapability::Workflow)
            && $user->hasOrganizationPermission($column->organization(), OrganizationPermission::ManageTeams);
    }

    public function delete(User $user, BoardColumn $column): bool
    {
        return $column->organization()->canUse(OrganizationCapability::Workflow)
            && $user->hasOrganizationPermission($column->organization(), OrganizationPermission::ManageTeams);
    }
}
