<?php

namespace App\Policies;

use App\Enums\OrganizationCapability;
use App\Enums\OrganizationPermission;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can delete the comment.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->feature->project->organization->canUse(OrganizationCapability::Boards)
            && ($user->is($comment->author)
                || $user->hasOrganizationPermission($comment->feature->project->organization, OrganizationPermission::ManageFeatures));
    }
}
