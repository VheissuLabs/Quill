<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::UpdateOrganization);
    }

    /**
     * Determine whether the user can enable or disable organization modules.
     */
    public function manageModules(User $user, Organization $organization): bool
    {
        return $organization->isOwner($user);
    }

    /**
     * Determine whether the user can leave the organization. The last owner cannot.
     */
    public function leave(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && (! $organization->isOwner($user) || $organization->hasOtherOwner($user));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::DeleteOrganization);
    }
}
