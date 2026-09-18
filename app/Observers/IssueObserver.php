<?php

namespace App\Observers;

use App\Events\IssuesChanged;
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

    public function saved(Issue $issue): void
    {
        $this->announce($issue);
    }

    public function deleted(Issue $issue): void
    {
        $this->announce($issue);
    }

    private function announce(Issue $issue): void
    {
        $issue->loadMissing('project');

        broadcast(new IssuesChanged(
            (int) $issue->project->organization_id,
            [(int) $issue->getKey()]
        ))->toOthers();
    }

    public function deleting(Issue $issue): void
    {
        Comment::withoutEvents(
            fn () => $issue->comments()->cursor()->each(fn (Comment $comment) => $comment->delete())
        );
    }
}
