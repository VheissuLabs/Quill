<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\OpenTicket;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Requests\Tickets\TicketIndexRequest;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\TicketDetailResource;
use App\Http\Resources\TicketResource;
use App\Models\Organization;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(TicketIndexRequest $request, Organization $currentOrganization): Response
    {
        return Inertia::render('tickets/Index', [
            'tickets' => Inertia::scroll(fn () => TicketResource::collection(
                $currentOrganization->tickets()->inInbox($request->user(), $request->filters())->paginate(30)
            )),
            'filters' => $request->filters(),
            'statuses' => TicketStatus::options(),
            'clients' => $currentOrganization->clients()->orderBy('name')->get(['id', 'name', 'slug']),
            'counts' => [
                'unassigned' => $currentOrganization->tickets()->whereNull('client_id')->whereIn('status', [TicketStatus::Open, TicketStatus::AwaitingCustomer])->count(),
                'ready' => $currentOrganization->tickets()->whereNotNull('issue_resolved_at')->count(),
            ],
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
            'ticket' => TicketDetailResource::make($ticket, $user)->resolve(),
            'comments' => Inertia::scroll(fn () => CommentResource::page($ticket->comments(), $user, true, true)),
            'mentionables' => $ticket->mentionableUsers()->map(fn ($member) => ['id' => $member->id, 'name' => $member->name]),
            'statuses' => TicketStatus::options(),
            'clients' => $currentOrganization->clients()->orderBy('name')->get(['id', 'name']),
            'projects' => $ticket->client?->projects->sortBy('name')->values()->map(fn ($project) => ['id' => $project->id, 'name' => $project->name]) ?? [],
            'otherTickets' => TicketResource::collection(
                $currentOrganization->tickets()
                    ->where('requester_email', $ticket->requester_email)
                    ->whereKeyNot($ticket->getKey())
                    ->with(['client', 'issue.project'])
                    ->latest('last_message_at')
                    ->limit(5)
                    ->get()
            ),
        ]);
    }

    public function update(UpdateTicketRequest $request, Organization $currentOrganization, Ticket $ticket): RedirectResponse
    {
        if ($request->has('client_id')) {
            $ticket->assignTo($request->filled('client_id') ? $currentOrganization->clients()->find($request->integer('client_id')) : null);
        }

        if ($request->has('status')) {
            $ticket->changeStatus(TicketStatus::from($request->validated('status')));
        }

        return back();
    }
}
