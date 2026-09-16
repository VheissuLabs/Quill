<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::UpdateOrganization);
    }

    public function manageModules(User $user, Organization $organization): bool
    {
        return $organization->isOwner($user);
    }

    public function leave(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && (! $organization->isOwner($user) || $organization->hasOtherOwner($user));
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::DeleteOrganization);
    }
}
