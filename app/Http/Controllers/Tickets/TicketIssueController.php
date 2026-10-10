<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Issues\CreateIssue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\ConvertTicketRequest;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketIssueController extends Controller
{
    public function store(ConvertTicketRequest $request, Organization $currentOrganization, Ticket $ticket, CreateIssue $createIssue): RedirectResponse
    {
        $issue = $request->filled('issue_id')
            ? Issue::query()->findOrFail($request->integer('issue_id'))
            : $createIssue->handle($currentOrganization->projects()->findOrFail($request->integer('project_id')), [
                'title' => $ticket->subject,
                'description' => $ticket->comments()->oldest('id')->value('body'),
                'created_by' => $request->user()->getKey(),
            ]);

        $ticket->linkTo($issue, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->filled('issue_id')
            ? __('Linked to :reference.', ['reference' => $issue->reference()])
            : __('Added to the backlog as :reference.', ['reference' => $issue->reference()])]);

        return back();
    }

    public function destroy(Organization $currentOrganization, Ticket $ticket): RedirectResponse
    {
        $ticket->unlink();

        return back();
    }
}
