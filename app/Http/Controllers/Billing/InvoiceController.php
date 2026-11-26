<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CreateInvoice;
use App\Actions\Billing\DeleteInvoice;
use App\Enums\InvoiceStatus;
use App\Events\ClientsChanged;
use App\Events\InvoicesChanged;
use App\Events\ProjectsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoiceIndexRequest;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Http\Requests\Billing\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\InvoiceSummaryResource;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(InvoiceIndexRequest $request, Organization $currentOrganization): Response
    {
        $filters = $request->filters();

        $invoices = $currentOrganization->invoices()
            ->with('client:id,name,slug')
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['client'], fn ($query, int $client) => $query->where('client_id', $client))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('invoices/Index', [
            'invoices' => InvoicesChanged::live(InvoiceListResource::collection($invoices)->resolve(), $currentOrganization->id, ClientsChanged::class),
            'summary' => InvoicesChanged::live(InvoiceSummaryResource::make($currentOrganization)->resolve(), $currentOrganization->id, ClientsChanged::class),
            'filters' => $filters,
            'statuses' => collect(InvoiceStatus::cases())->map(fn (InvoiceStatus $status) => ['value' => $status->value, 'label' => $status->label()])->all(),
            'clients' => ClientsChanged::live($currentOrganization->clients()->orderBy('name')->get(['id', 'name']), $currentOrganization->id),
            'projects' => ProjectsChanged::live($currentOrganization->projects()->whereNotNull('client_id')->with('client:id,billed_through_client_id')->orderBy('name')->get()
                ->map(fn ($project) => ['id' => $project->id, 'name' => $project->name, 'billToClientId' => $project->client->billed_through_client_id ?? $project->client_id])->all(), $currentOrganization->id, ClientsChanged::class),
        ]);
    }

    public function store(StoreInvoiceRequest $request, Organization $currentOrganization, CreateInvoice $createInvoice): RedirectResponse
    {
        $invoice = $createInvoice->handle($currentOrganization, $currentOrganization->clients()->findOrFail($request->integer('client_id')), $request->invoiceAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice created.')]);

        return to_route('invoices.show', ['invoice' => $invoice->id]);
    }

    public function show(Organization $currentOrganization, Invoice $invoice): Response
    {
        return Inertia::render('invoices/Show', [
            'invoice' => InvoicesChanged::live(InvoiceResource::make($invoice->load(['client', 'organization', 'lines.issue.project', 'payments'])), $currentOrganization->id),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Organization $currentOrganization, Invoice $invoice): RedirectResponse
    {
        $invoice->update($request->validated());
        $invoice->recalculate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice updated.')]);

        return to_route('invoices.show', ['invoice' => $invoice->id]);
    }

    public function destroy(Organization $currentOrganization, Invoice $invoice, DeleteInvoice $deleteInvoice): RedirectResponse
    {
        $deleteInvoice->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice deleted.')]);

        return to_route('invoices.index');
    }
}
