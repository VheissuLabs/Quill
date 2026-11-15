<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\SendInvoice;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvoiceSendController extends Controller
{
    public function store(Organization $currentOrganization, Invoice $invoice, SendInvoice $sendInvoice): RedirectResponse
    {
        $sendInvoice->handle($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice sent.')]);

        return to_route('invoices.show', ['invoice' => $invoice->id]);
    }
}
