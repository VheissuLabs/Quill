<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\User;

class IssuePolicy
{
    /**
     * Determine whether the user can view the organization's issues.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can view the issue.
     */
    public function view(User $user, Issue $issue): bool
    {
        return $user->can('view', $issue->project);
    }

    /**
     * Determine whether the user can create issues in the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user can edit the issue, including its status and assignment.
     */
    public function update(User $user, Issue $issue): bool
    {
        return $user->belongsToOrganization($issue->project->organization);
    }

    /**
     * Determine whether the user can delete the issue.
     */
    public function delete(User $user, Issue $issue): bool
    {
        return $user->hasOrganizationPermission($issue->project->organization, OrganizationPermission::ManageIssues);
    }
}
