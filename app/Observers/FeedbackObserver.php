<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Feedback;
use App\Support\RichText;

class FeedbackObserver
{
    public function saving(Feedback $feedback): void
    {
        if ($feedback->isDirty('description') && $feedback->description !== null) {
            $feedback->description = RichText::sanitize($feedback->description);
        }
    }

    public function deleting(Feedback $feedback): void
    {
        Comment::withoutEvents(
            fn () => $feedback->comments()->cursor()->each(fn (Comment $comment) => $comment->delete())
        );
    }
}
