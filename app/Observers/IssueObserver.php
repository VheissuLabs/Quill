<?php

namespace App\Observers;

use App\Enums\IssueStatus;
use App\Enums\TicketStatus;
use App\Events\IssuesChanged;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Ticket;
use App\Notifications\TicketReadyToReply;
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

        if ($issue->wasChanged('status')) {
            $this->updateLinkedTickets($issue);
        }
    }

    private function updateLinkedTickets(Issue $issue): void
    {
        if ($issue->status !== IssueStatus::Done) {
            $issue->tickets()->whereNotNull('issue_resolved_at')->update(['issue_resolved_at' => null]);

            return;
        }

        $tickets = $issue->tickets()
            ->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingCustomer])
            ->whereNull('issue_resolved_at')
            ->with(['reporter', 'organization'])
            ->get();

        $issue->tickets()->whereKey($tickets->modelKeys())->update(['issue_resolved_at' => now()]);

        $tickets
            ->filter(fn (Ticket $ticket) => $ticket->reporter !== null)
            ->groupBy('reporter_id')
            ->each(fn ($reported) => $reported->first()->reporter->notify(new TicketReadyToReply($issue, $reported)));
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
