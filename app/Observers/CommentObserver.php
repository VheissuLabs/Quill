<?php

namespace App\Observers;

use App\Events\FeedbackCommented;
use App\Models\Comment;
use App\Models\Feedback;
use App\Support\RichText;

class CommentObserver
{
    /**
     * The editor writes HTML and everything else wants words: search matches on the text, an
     * excerpt reads from it, and the slash commands are read out of it a line at a time. Doing
     * it on the way in means no caller can save one without the other.
     */
    public function saving(Comment $comment): void
    {
        if ($comment->isDirty('body')) {
            $comment->body = RichText::sanitize($comment->body);
            $comment->body_text = RichText::toText($comment->body);
        }
    }

    public function created(Comment $comment): void
    {
        $this->announce($comment);
    }

    public function deleting(Comment $comment): void
    {
        $this->recordDeletion($comment);
    }

    public function deleted(Comment $comment): void
    {
        $this->announce($comment);
    }

    /**
     * Recorded before the row goes, because replies are cascaded by the database and Eloquent
     * never sees them leave. Deleting the top of a thread takes the whole conversation with it,
     * so the whole conversation is what gets written down.
     */
    private function recordDeletion(Comment $comment): void
    {
        $comment->loadMissing(['commentable', 'author', 'replies.author']);

        $replies = $comment->replies
            ->map(fn (Comment $reply) => ($reply->author->name ?? __('Someone')).': '.$reply->body_text)
            ->implode("\n\n");

        activity('comments')
            ->on($comment->commentable)
            ->event('comment_deleted')
            ->withChanges(['attributes' => array_filter([
                'comment' => $comment->body_text,
                'author' => $comment->author?->name,
                'is_internal' => $comment->is_internal,
                'replies' => $replies === '' ? null : $replies,
            ], fn ($value) => $value !== null)])
            ->log('comment_deleted');
    }

    /**
     * A board shows how many comments a request has, so the count moves for everyone watching
     * either the board or the request itself.
     */
    private function announce(Comment $comment): void
    {
        if ($comment->commentable_type !== (new Feedback)->getMorphClass()) {
            return;
        }

        $projectId = Feedback::query()->whereKey($comment->commentable_id)->value('project_id');

        if ($projectId !== null) {
            broadcast(new FeedbackCommented((int) $projectId, (int) $comment->commentable_id))->toOthers();
        }
    }
}
