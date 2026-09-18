<?php

namespace App\Observers;

use App\Events\FeatureCommented;
use App\Models\Comment;
use App\Models\Feature;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        $this->announce($comment);
    }

    public function deleted(Comment $comment): void
    {
        $this->announce($comment);
    }

    /**
     * A board shows how many comments a request has, so the count moves for everyone watching
     * either the board or the request itself.
     */
    private function announce(Comment $comment): void
    {
        if ($comment->commentable_type !== (new Feature)->getMorphClass()) {
            return;
        }

        $projectId = Feature::query()->whereKey($comment->commentable_id)->value('project_id');

        if ($projectId !== null) {
            broadcast(new FeatureCommented((int) $projectId, (int) $comment->commentable_id))->toOthers();
        }
    }
}
