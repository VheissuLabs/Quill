<?php

namespace App\Http\Controllers\Clients;

use App\Actions\Billing\StopBillingCycle;
use App\Enums\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
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
            'clients' => ClientResource::collection($currentOrganization->clients()->withCount('projects')->orderBy('name')->get()),
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
            'client' => ClientResource::make($client->load(['contacts' => fn ($query) => $query->orderBy('name')])),
            'projects' => ProjectResource::collection($client->projects()->visibleTo($user)->orderBy('name')->get()),
            'tickets' => LinkedTicketResource::forClient($client, $user),
            'invoices' => $currentOrganization->allowsInvoiceManagementBy($user)
                ? InvoiceListResource::collection($client->invoices()->with('client:id,name,slug')->orderByDesc('issue_date')->orderByDesc('id')->get()->sortBy(fn (Invoice $invoice) => $invoice->id === $openCycleInvoiceId ? 0 : 1)->values())->resolve()
                : null,
        ]);
    }

    public function edit(Organization $currentOrganization, Client $client): Response
    {
        return Inertia::render('clients/Edit', [
            'client' => ClientResource::make($client->load('domains')),
            'billing' => $currentOrganization->allowsInvoiceManagementBy(auth()->user()) ? [
                'rate' => $client->billing_rate,
                'contactId' => $client->billing_contact_id,
                'billedThroughClientId' => $client->billed_through_client_id,
                'cycle' => $client->billing_cycle?->value,
                'cycleStartsOn' => $client->billing_cycle_starts_on?->toDateString(),
                'ccEmails' => $client->billing_cc_emails ?? [],
                'paymentTermsDays' => $client->payment_terms_days,
                'currentPeriod' => $client->currentBillingPeriod(),
                'cycles' => array_map(fn (BillingCycle $cycle) => ['value' => $cycle->value, 'label' => $cycle->label()], BillingCycle::cases()),
                'contacts' => $client->contacts()->orderBy('name')->get(['id', 'name', 'email']),
                'billedThroughOptions' => $currentOrganization->clients()->whereKeyNot($client->id)->orderBy('name')->get(['id', 'name']),
            ] : null,
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
        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Client deleted.')]);

        return to_route('clients.index');
    }
}
