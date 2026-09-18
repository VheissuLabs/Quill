<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Issue;
use App\Support\RichText;

class IssueObserver
{
    public function saving(Issue $issue): void
    {
        if ($issue->isDirty('description') && $issue->description !== null) {
            $issue->description = RichText::sanitize($issue->description);
        }
    }

    public function deleting(Issue $issue): void
    {
        Comment::withoutEvents(
            fn () => $issue->comments()->cursor()->each(fn (Comment $comment) => $comment->delete())
        );
    }
}
