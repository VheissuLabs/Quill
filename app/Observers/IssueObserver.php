<?php

namespace App\Observers;

use App\Models\Issue;

class IssueObserver
{
    public function deleting(Issue $issue): void
    {
        $issue->comments()->cursor()->each(fn ($comment) => $comment->delete());
    }
}
