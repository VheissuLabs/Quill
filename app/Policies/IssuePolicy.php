<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\User;

class IssuePolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Issues)
            && $user->belongsToOrganization($organization);
    }

    public function view(User $user, Issue $issue): bool
    {
        return $issue->project->organization->canUse(OrganizationCapability::Issues)
            && $user->can('view', $issue->project);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $organization->canUse(OrganizationCapability::Issues)
            && $user->belongsToOrganization($organization);
    }

    public function update(User $user, Issue $issue): bool
    {
        return $issue->project->organization->canUse(OrganizationCapability::Issues)
            && $user->belongsToOrganization($issue->project->organization);
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $issue->project->organization->canUse(OrganizationCapability::Issues)
            && $user->hasOrganizationPermission($issue->project->organization, OrganizationPermission::ManageIssues);
    }
}
