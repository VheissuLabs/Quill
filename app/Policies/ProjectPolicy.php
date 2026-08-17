<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function view(User $user, Project $project): Response
    {
        $organization = $user->currentOrganization;

        return $organization !== null
            && $project->organization_id === $organization->id
            && $user->belongsToOrganization($organization)
                ? Response::allow()
                : Response::denyAsNotFound();
    }
}
