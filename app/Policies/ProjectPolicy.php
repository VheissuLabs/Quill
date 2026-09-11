<?php

namespace App\Policies;

use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view the organization's projects.
     */
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Determine whether the user (or guest) can view the project and its board.
     */
    public function view(?User $user, Project $project): bool
    {
        return Project::query()
            ->whereKey($project->id)
            ->visibleTo($user)
            ->exists();
    }

    /**
     * Determine whether the user can create projects in the organization.
     */
    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageProjects);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->hasOrganizationPermission($project->organization, OrganizationPermission::ManageProjects);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasOrganizationPermission($project->organization, OrganizationPermission::ManageProjects);
    }
}
