<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Ticket;
use App\Models\User;

class CommentPolicy
{
    public function delete(User $user, Comment $comment): bool
    {
        $commentable = $comment->commentable;

        if ($commentable instanceof Ticket) {
            return $comment->is_internal
                && ($user->is($comment->author) || $user->can('update', $commentable));
        }

        $comment->loadMissing('commentable.project.organization');
        $commentable = $comment->commentable;

        if (! $commentable instanceof Feedback && ! $commentable instanceof Issue) {
            return false;
        }

        [$capability, $permission] = $commentable instanceof Feedback
            ? [OrganizationCapability::Boards, OrganizationPermission::ManageFeedback]
            : [OrganizationCapability::Issues, OrganizationPermission::ManageIssues];

        $organization = $commentable->project->organization;

        return $organization->canUse($capability)
            && ($user->is($comment->author)
                || $user->hasOrganizationPermission($organization, $permission));
    }
}
