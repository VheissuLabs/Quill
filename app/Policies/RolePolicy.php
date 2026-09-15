<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageRoles);
    }

    public function update(User $user, Role $role): bool
    {
        return ! $role->is_owner && $this->create($user, $this->organizationOf($role));
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $role->is_owner && $this->create($user, $this->organizationOf($role));
    }

    protected function organizationOf(Role $role): Organization
    {
        return Organization::findOrFail($role->getAttribute(config('permission.column_names.team_foreign_key')));
    }
}
