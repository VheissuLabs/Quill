<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\OrganizationMembership;
use App\Models\User;
use Spatie\Permission\Models\Role;

class OrganizationMembershipPolicy
{
    public function update(User $user, OrganizationMembership $membership, ?Role $role = null): bool
    {
        $organization = $membership->organization;

        if (! $user->hasOrganizationPermission($organization, OrganizationPermission::UpdateMember)) {
            return false;
        }

        if ($organization->isOwner($membership->user) && ! $organization->ownerMayStepDown($user, $membership->user)) {
            return false;
        }

        return $role === null || ! $organization->isOwnerRole($role) || $organization->isOwner($user);
    }

    public function delete(User $user, OrganizationMembership $membership): bool
    {
        $organization = $membership->organization;

        return $user->hasOrganizationPermission($organization, OrganizationPermission::RemoveMember)
            && (! $organization->isOwner($membership->user) || $organization->ownerMayStepDown($user, $membership->user));
    }
}
