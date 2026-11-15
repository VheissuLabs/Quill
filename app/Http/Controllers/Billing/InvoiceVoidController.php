<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\VoidInvoice;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvoiceVoidController extends Controller
{
    public function store(Organization $currentOrganization, Invoice $invoice, VoidInvoice $voidInvoice): RedirectResponse
    {
        $voidInvoice->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice voided.')]);

        return to_route('invoices.show', ['invoice' => $invoice->id]);
    }
}
