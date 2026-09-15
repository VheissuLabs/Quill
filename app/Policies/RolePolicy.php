<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can see the roles the organization defines.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can define a role for the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageRoles);
    }

    /**
     * Determine whether the user can change the role. The owner role is locked.
     */
    public function update(User $user, Role $role): bool
    {
        return ! $role->is_owner && $this->create($user, $this->organizationOf($role));
    }

    /**
     * Determine whether the user can delete the role. The owner role is locked.
     */
    public function delete(User $user, Role $role): bool
    {
        return ! $role->is_owner && $this->create($user, $this->organizationOf($role));
    }

    /**
     * Get the organization that defines the role.
     */
    protected function organizationOf(Role $role): Organization
    {
        return Organization::findOrFail($role->getAttribute(config('permission.column_names.team_foreign_key')));
    }
}
