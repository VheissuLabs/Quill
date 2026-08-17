<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IssuePolicy
{
    public function view(User $user, Issue $issue): Response
    {
        $organization = $user->currentOrganization;

        $sameOrganization = $organization !== null
            && $issue->organization_id === $organization->id
            && $user->belongsToOrganization($organization);

        if (! $sameOrganization) {
            return Response::denyAsNotFound();
        }

        return $user->isClientContact($organization)
            ? Response::deny()
            : Response::allow();
    }
}
