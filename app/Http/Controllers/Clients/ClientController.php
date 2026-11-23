<?php

namespace App\Http\Controllers\Clients;

use App\Actions\Billing\StopBillingCycle;
use App\Enums\ProjectVisibility;
use App\Events\ClientsChanged;
use App\Events\InvoicesChanged;
use App\Events\IssuesChanged;
use App\Events\ProjectsChanged;
use App\Events\TicketsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientBillingResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\LinkedTicketResource;
use App\Http\Resources\ProjectResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Organization $currentOrganization): Response
    {
        return Inertia::render('clients/Index', [
            'clients' => ClientsChanged::live(ClientResource::collection($currentOrganization->clients()->withCount('projects')->orderBy('name')->get()), $currentOrganization->id, ProjectsChanged::class),
        ]);
    }

    public function store(ClientRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->clients()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client created.')]);

        return to_route('clients.index');
    }

    public function show(Organization $currentOrganization, Client $client): Response
    {
        $user = auth()->user();
        $openCycleInvoiceId = $client->openCycleInvoice()?->id;

        return Inertia::render('clients/Show', [
            'client' => ClientsChanged::live(ClientResource::make($client->load(['contacts' => fn ($query) => $query->orderBy('name')])), $currentOrganization->id),
            'projects' => ProjectsChanged::live(ProjectResource::collection($client->projects()->visibleTo($user)->orderBy('name')->get()), $currentOrganization->id),
            'tickets' => TicketsChanged::live(LinkedTicketResource::forClient($client, $user), $currentOrganization->id, IssuesChanged::class),
            'invoices' => InvoicesChanged::live($currentOrganization->allowsInvoiceManagementBy($user)
                ? InvoiceListResource::collection($client->invoices()->with('client:id,name,slug')->orderByDesc('issue_date')->orderByDesc('id')->get()->sortBy(fn (Invoice $invoice) => $invoice->id === $openCycleInvoiceId ? 0 : 1)->values())->resolve()
                : null, $currentOrganization->id),
            'visibilities' => ProjectVisibility::options(),
        ]);
    }

    public function edit(Organization $currentOrganization, Client $client): Response
    {
        return Inertia::render('clients/Edit', [
            'client' => ClientResource::make($client->load('domains')),
            'billing' => $currentOrganization->allowsInvoiceManagementBy(auth()->user()) ? ClientBillingResource::make($client)->resolve() : null,
        ]);
    }

    public function update(ClientRequest $request, Organization $currentOrganization, Client $client, StopBillingCycle $stopBillingCycle): RedirectResponse
    {
        $hadCycle = $client->billing_cycle !== null;

        $client->fill($request->validated());

        if (! $hadCycle && $client->billing_cycle !== null) {
            $client->billing_opened_through = null;
        }

        $client->save();

        if ($hadCycle && $request->validated('billing_cycle', false) === null) {
            $stopBillingCycle->handle($client);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client updated.')]);

        return to_route('clients.edit', ['client' => $client->slug]);
    }

    public function destroy(Organization $currentOrganization, Client $client): RedirectResponse
    {
        $client->deleteKeepingRecords();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index');
    }
}
