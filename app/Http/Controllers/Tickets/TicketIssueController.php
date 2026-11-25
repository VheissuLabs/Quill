<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\ConvertTicketToIssue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\ConvertTicketRequest;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketIssueController extends Controller
{
    public function store(ConvertTicketRequest $request, Organization $currentOrganization, Ticket $ticket, ConvertTicketToIssue $convert): RedirectResponse
    {
        $issue = $convert->handle(
            $ticket,
            $request->user(),
            $request->filled('issue_id') ? $request->integer('issue_id') : null,
            $request->filled('project_id') ? $request->integer('project_id') : null,
        );

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
