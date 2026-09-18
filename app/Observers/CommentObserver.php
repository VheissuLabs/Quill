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
        $this->recordDeletion($comment);
        $this->announce($comment);
    }

    private function recordDeletion(Comment $comment): void
    {
        $comment->loadMissing(['commentable', 'author']);

        activity('comments')
            ->on($comment->commentable)
            ->event('comment_deleted')
            ->withChanges(['attributes' => [
                'comment' => $comment->body,
                'author' => $comment->author?->name,
                'is_internal' => $comment->is_internal,
            ]])
            ->log('comment_deleted');
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
