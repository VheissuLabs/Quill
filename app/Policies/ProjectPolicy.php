<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function view(?User $user, Project $project): bool
    {
        return Project::query()
            ->whereKey($project->id)
            ->visibleTo($user)
            ->exists();
    }

    public function viewFeedbackBoard(?User $user, Project $project): bool
    {
        return $project->organization->canUse(OrganizationCapability::Boards)
            && $this->view($user, $project);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $user->hasOrganizationPermission($organization, OrganizationPermission::ManageProjects);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasOrganizationPermission($project->organization, OrganizationPermission::ManageProjects);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasOrganizationPermission($project->organization, OrganizationPermission::ManageProjects);
    }
}
