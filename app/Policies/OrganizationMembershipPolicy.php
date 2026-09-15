<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\OrganizationMembership;
use App\Models\User;
use Spatie\Permission\Models\Role;

class OrganizationMembershipPolicy
{
    /**
     * Determine whether the user can give the membership the given role. An owner's membership
     * is theirs alone to change, and only while another owner remains; only an owner can make
     * someone else an owner.
     */
    public function update(User $user, OrganizationMembership $membership, ?Role $role = null): bool
    {
        $organization = $membership->organization;

        if (! $user->hasOrganizationPermission($organization, OrganizationPermission::UpdateMember)) {
            return false;
        }

        if ($organization->isOwner($membership->user) && ! $organization->ownerMayStepDown($user, $membership->user)) {
            return false;
        }

        return $role === null || ! $role->is_owner || $organization->isOwner($user);
    }

    /**
     * Determine whether the user can end the membership. An owner can only be removed by
     * themselves, and only while another owner remains.
     */
    public function delete(User $user, OrganizationMembership $membership): bool
    {
        $organization = $membership->organization;

        return $user->hasOrganizationPermission($organization, OrganizationPermission::RemoveMember)
            && (! $organization->isOwner($membership->user) || $organization->ownerMayStepDown($user, $membership->user));
    }
}
