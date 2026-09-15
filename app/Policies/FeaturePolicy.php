<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Feature;
use App\Models\Project;
use App\Models\User;

class FeaturePolicy
{
    /**
     * Determine whether the user (or guest) can view the feature.
     */
    public function view(?User $user, Feature $feature): bool
    {
        return $user?->can('view', $feature->project) ?? $feature->project->isPublic();
    }

    /**
     * Determine whether the user can request a feature on the project.
     */
    public function create(User $user, Project $project): bool
    {
        return $user->can('view', $project);
    }

    /**
     * Determine whether the user can edit the feature's title and description.
     */
    public function update(User $user, Feature $feature): bool
    {
        return $user->is($feature->author) || $this->manage($user, $feature);
    }

    /**
     * Determine whether the user can change the feature's status.
     */
    public function updateStatus(User $user, Feature $feature): bool
    {
        return $this->manage($user, $feature);
    }

    /**
     * Determine whether the user can delete the feature.
     */
    public function delete(User $user, Feature $feature): bool
    {
        return $user->is($feature->author) || $this->manage($user, $feature);
    }

    /**
     * Determine whether the user can convert the feature into an issue. Declined and completed
     * requests are settled and stay off the board.
     */
    public function convert(User $user, Feature $feature): bool
    {
        return $this->manage($user, $feature) && ! $feature->status->isSettled();
    }

    /**
     * Determine whether the user can vote for the feature. Settled requests no longer take votes.
     */
    public function vote(User $user, Feature $feature): bool
    {
        return ! $feature->status->isSettled() && $user->can('view', $feature->project);
    }

    /**
     * Determine whether the user can comment on the feature.
     */
    public function comment(User $user, Feature $feature): bool
    {
        return $user->can('view', $feature->project);
    }

    /**
     * Determine whether the user can leave comments only organization members see.
     */
    public function commentInternally(User $user, Feature $feature): bool
    {
        return $user->belongsToOrganization($feature->project->organization);
    }

    /**
     * Determine whether the user manages features for the feature's organization.
     */
    protected function manage(User $user, Feature $feature): bool
    {
        return $user->hasOrganizationPermission($feature->project->organization, OrganizationPermission::ManageFeatures);
    }
}
