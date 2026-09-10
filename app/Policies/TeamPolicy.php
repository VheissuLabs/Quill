<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $team): bool
    {
        return $this->ownerOrGranted($user, $team, 'team:update');
    }

    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal && $this->ownerOrGranted($user, $team, 'team:delete');
    }

    public function leave(User $user, Team $team): bool
    {
        return ! $team->is_personal
            && $user->belongsToTeam($team)
            && ! $user->ownsTeam($team);
    }

    public function addMember(User $user, Team $team): bool
    {
        return $this->ownerOrGranted($user, $team, 'member:add');
    }

    public function removeMember(User $user, Team $team, ?User $member = null): bool
    {
        if ($member !== null && $team->owner_id === $member->id) {
            return false;
        }

        return $this->ownerOrGranted($user, $team, 'member:remove');
    }

    public function inviteMember(User $user, Team $team): bool
    {
        return $this->ownerOrGranted($user, $team, 'invitation:create');
    }

    public function cancelInvitation(User $user, Team $team): bool
    {
        return $this->ownerOrGranted($user, $team, 'invitation:cancel');
    }

    /**
     * Owning the team is enough on its own. Ownership is a column rather than a
     * role, and a personal team's owner belongs to no organization to be granted
     * anything by.
     */
    protected function ownerOrGranted(User $user, Team $team, string $permission): bool
    {
        return $user->ownsTeam($team) || $user->can($permission);
    }
}
