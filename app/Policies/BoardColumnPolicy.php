<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\BoardColumn;
use App\Models\Team;
use App\Models\User;

class BoardColumnPolicy
{
    /**
     * Determine whether the user can add columns to the team's board.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->hasOrganizationPermission($team->organization, OrganizationPermission::ManageTeams);
    }

    /**
     * Determine whether the user can rename, remap or reorder the column.
     */
    public function update(User $user, BoardColumn $column): bool
    {
        return $user->hasOrganizationPermission($column->team->organization, OrganizationPermission::ManageTeams);
    }

    /**
     * Determine whether the user can remove the column.
     */
    public function delete(User $user, BoardColumn $column): bool
    {
        return $user->hasOrganizationPermission($column->team->organization, OrganizationPermission::ManageTeams);
    }
}
