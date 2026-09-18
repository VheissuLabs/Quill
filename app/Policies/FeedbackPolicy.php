<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\User;

class FeedbackPolicy
{
    public function view(?User $user, Feedback $feedback): bool
    {
        return $feedback->project->organization->canUse(OrganizationCapability::Boards)
            && ($user?->can('view', $feedback->project) ?? $feedback->project->isPublic());
    }

    public function create(User $user, Project $project): bool
    {
        return $project->organization->canUse(OrganizationCapability::Boards)
            && $user->can('view', $project);
    }

    public function update(User $user, Feedback $feedback): bool
    {
        return $feedback->project->organization->canUse(OrganizationCapability::Boards)
            && ($user->is($feedback->author) || $this->manage($user, $feedback));
    }

    public function updateStatus(User $user, Feedback $feedback): bool
    {
        return $this->manage($user, $feedback);
    }

    public function delete(User $user, Feedback $feedback): bool
    {
        return $feedback->project->organization->canUse(OrganizationCapability::Boards)
            && ($user->is($feedback->author) || $this->manage($user, $feedback));
    }

    public function convert(User $user, Feedback $feedback): bool
    {
        return $this->manage($user, $feedback)
            && $feedback->project->organization->canUse(OrganizationCapability::Issues)
            && ! $feedback->status->isSettled();
    }

    public function vote(User $user, Feedback $feedback): bool
    {
        return $feedback->project->organization->canUse(OrganizationCapability::Voting)
            && ! $feedback->status->isSettled()
            && $user->can('view', $feedback->project);
    }

    public function comment(User $user, Feedback $feedback): bool
    {
        return $feedback->project->organization->canUse(OrganizationCapability::Boards)
            && $user->can('view', $feedback->project);
    }

    public function commentInternally(User $user, Feedback $feedback): bool
    {
        return $feedback->project->organization->canUse(OrganizationCapability::Boards)
            && $user->belongsToOrganization($feedback->project->organization);
    }

    protected function manage(User $user, Feedback $feedback): bool
    {
        return $feedback->project->organization->canUse(OrganizationCapability::Boards)
            && $user->hasOrganizationPermission($feedback->project->organization, OrganizationPermission::ManageFeedback);
    }
}
