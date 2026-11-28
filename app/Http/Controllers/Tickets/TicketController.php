<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\OpenTicket;
use App\Enums\TicketStatus;
use App\Events\ClientsChanged;
use App\Events\IssuesChanged;
use App\Events\MembersChanged;
use App\Events\ProjectsChanged;
use App\Events\TicketsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\TicketIndexRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\TicketDetailResource;
use App\Http\Resources\TicketResource;
use App\Models\Organization;
use App\Models\Ticket;
use App\Support\Window;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(TicketIndexRequest $request, Organization $currentOrganization): Response
    {
        return Inertia::render('tickets/Index', [
            'tickets' => TicketsChanged::live(fn () => Window::fromRequest($request, TicketResource::WINDOW_STEP)->of(
                $currentOrganization->tickets()->inInbox($request->user(), $request->filters()),
                fn (Ticket $ticket) => TicketResource::make($ticket)->resolve(),
            ), $currentOrganization->id, IssuesChanged::class),
            'filters' => $request->filters(),
            'statuses' => TicketStatus::options(),
            'clients' => ClientsChanged::live($currentOrganization->clients()->orderBy('name')->get(['id', 'name', 'slug']), $currentOrganization->id),
            'counts' => TicketsChanged::live([
                'unassigned' => $currentOrganization->tickets()->whereNull('client_id')->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingCustomer])->count(),
                'ready' => $currentOrganization->tickets()->whereNotNull('issue_resolved_at')->count(),
            ], $currentOrganization->id, IssuesChanged::class),
        ]);
    }

    public function store(StoreTicketRequest $request, Organization $currentOrganization, OpenTicket $openTicket): RedirectResponse
    {
        $ticket = $openTicket->handle($currentOrganization, $request->user(), $request->ticketAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':reference opened.', ['reference' => $ticket->reference()])]);

        return to_route('tickets.show', ['ticket' => $ticket]);
    }

    public function show(Organization $currentOrganization, Ticket $ticket): Response
    {
        $user = auth()->user();

        $ticket->load(['client.projects', 'issue.project', 'reporter', 'creator']);

        return Inertia::render('tickets/Show', [
            'ticket' => TicketsChanged::live(TicketDetailResource::make($ticket, $user)->resolve(), $currentOrganization->id, IssuesChanged::class, ClientsChanged::class),
            'comments' => TicketsChanged::live(fn () => CommentResource::window($ticket->comments(), $user, true, true), $currentOrganization->id),
            'mentionables' => MembersChanged::live($ticket->mentionableUsers()->map(fn ($member) => ['id' => $member->id, 'name' => $member->name]), $currentOrganization->id),
            'statuses' => TicketStatus::options(),
            'clients' => ClientsChanged::live($currentOrganization->clients()->orderBy('name')->get(['id', 'name']), $currentOrganization->id),
            'projects' => ProjectsChanged::live($ticket->client?->projects->sortBy('name')->values()->map(fn ($project) => ['id' => $project->id, 'name' => $project->name]) ?? [], $currentOrganization->id, TicketsChanged::class),
            'otherTickets' => TicketsChanged::live(TicketResource::collection(
                $currentOrganization->tickets()
                    ->where('requester_email', $ticket->requester_email)
                    ->whereKeyNot($ticket->getKey())
                    ->with(['client', 'issue.project'])
                    ->latest('last_message_at')
                    ->limit(5)
                    ->get()
            ), $currentOrganization->id, IssuesChanged::class),
        ]);
    }

    public function update(UpdateTicketRequest $request, Organization $currentOrganization, Ticket $ticket): RedirectResponse
    {
        $ticket->applyChanges($request->validated());

        return back();
    }

    public function destroy(Organization $currentOrganization, Ticket $ticket): RedirectResponse
    {
        $ticket->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':reference deleted.', ['reference' => $ticket->reference()])]);

        return to_route('tickets.index');
    }
}
