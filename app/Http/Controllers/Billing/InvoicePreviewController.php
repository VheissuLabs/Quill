<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicInvoiceResource;
use App\Models\Invoice;
use App\Models\Organization;
use Inertia\Inertia;
use Inertia\Response;

class InvoicePreviewController extends Controller
{
    public function __invoke(Organization $currentOrganization, Invoice $invoice): Response
    {
        return Inertia::render('invoices/Public', [
            'invoice' => PublicInvoiceResource::make($invoice->load(['organization', 'client', 'contact', 'lines.issue.project', 'payments'])),
            'previewBackUrl' => route('invoices.show', ['current_organization' => $currentOrganization, 'invoice' => $invoice]),
        ]);
    }
}
