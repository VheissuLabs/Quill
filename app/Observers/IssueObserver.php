<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Issue;

class IssueObserver
{
    public function deleting(Issue $issue): void
    {
        Comment::withoutEvents(
            fn () => $issue->comments()->cursor()->each(fn (Comment $comment) => $comment->delete())
        );
    }
}
