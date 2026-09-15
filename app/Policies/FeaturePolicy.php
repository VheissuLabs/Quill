<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Feature;
use App\Models\Project;
use App\Models\User;

class FeaturePolicy
{
    public function view(?User $user, Feature $feature): bool
    {
        return $feature->project->organization->canUse(OrganizationCapability::Boards)
            && ($user?->can('view', $feature->project) ?? $feature->project->isPublic());
    }

    public function create(User $user, Project $project): bool
    {
        return $project->organization->canUse(OrganizationCapability::Boards)
            && $user->can('view', $project);
    }

    public function update(User $user, Feature $feature): bool
    {
        return $feature->project->organization->canUse(OrganizationCapability::Boards)
            && ($user->is($feature->author) || $this->manage($user, $feature));
    }

    public function updateStatus(User $user, Feature $feature): bool
    {
        return $this->manage($user, $feature);
    }

    public function delete(User $user, Feature $feature): bool
    {
        return $feature->project->organization->canUse(OrganizationCapability::Boards)
            && ($user->is($feature->author) || $this->manage($user, $feature));
    }

    public function convert(User $user, Feature $feature): bool
    {
        return $this->manage($user, $feature)
            && $feature->project->organization->canUse(OrganizationCapability::Issues)
            && ! $feature->status->isSettled();
    }

    public function vote(User $user, Feature $feature): bool
    {
        return $feature->project->organization->canUse(OrganizationCapability::Voting)
            && ! $feature->status->isSettled()
            && $user->can('view', $feature->project);
    }

    public function comment(User $user, Feature $feature): bool
    {
        return $feature->project->organization->canUse(OrganizationCapability::Boards)
            && $user->can('view', $feature->project);
    }

    public function commentInternally(User $user, Feature $feature): bool
    {
        return $feature->project->organization->canUse(OrganizationCapability::Boards)
            && $user->belongsToOrganization($feature->project->organization);
    }

    protected function manage(User $user, Feature $feature): bool
    {
        return $feature->project->organization->canUse(OrganizationCapability::Boards)
            && $user->hasOrganizationPermission($feature->project->organization, OrganizationPermission::ManageFeatures);
    }
}
