<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Feedback;

class FeedbackObserver
{
    public function deleting(Feedback $feedback): void
    {
        Comment::withoutEvents(
            fn () => $feedback->comments()->cursor()->each(fn (Comment $comment) => $comment->delete())
        );
    }
}
