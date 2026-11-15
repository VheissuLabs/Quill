<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\DuplicateInvoice;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvoiceDuplicateController extends Controller
{
    public function store(Organization $currentOrganization, Invoice $invoice, DuplicateInvoice $duplicateInvoice): RedirectResponse
    {
        $copy = $duplicateInvoice->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice duplicated.')]);

        return to_route('invoices.show', ['invoice' => $copy->id]);
    }
}
