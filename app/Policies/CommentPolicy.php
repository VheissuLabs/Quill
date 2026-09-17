<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\User;

class CommentPolicy
{
    public function delete(User $user, Comment $comment): bool
    {
        $comment->loadMissing('commentable.project.organization');
        $commentable = $comment->commentable;

        if (! $commentable instanceof Feature && ! $commentable instanceof Issue) {
            return false;
        }

        [$capability, $permission] = $commentable instanceof Feature
            ? [OrganizationCapability::Boards, OrganizationPermission::ManageFeatures]
            : [OrganizationCapability::Issues, OrganizationPermission::ManageIssues];

        $organization = $commentable->project->organization;

        return $organization->canUse($capability)
            && ($user->is($comment->author)
                || $user->hasOrganizationPermission($organization, $permission));
    }
}
