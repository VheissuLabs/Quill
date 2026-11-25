<?php

namespace App\Actions\Tickets;

use App\Actions\Issues\CreateIssue;
use App\Models\Issue;
use App\Models\Ticket;
use App\Models\User;

class ConvertTicketToIssue
{
    public function __construct(private CreateIssue $createIssue) {}

    public function handle(Ticket $ticket, User $reporter, ?int $issueId, ?int $projectId): Issue
    {
        $issue = $issueId !== null
            ? Issue::query()->whereIn('project_id', $ticket->organization->projects()->select('projects.id'))->findOrFail($issueId)
            : $this->createIssue->handle($ticket->organization->projects()->findOrFail($projectId), [
                'title' => $ticket->subject,
                'description' => $ticket->comments()->oldest('id')->value('body'),
                'created_by' => $reporter->getKey(),
            ]);

        $ticket->linkTo($issue, $reporter);

        return $issue;
    }
}
