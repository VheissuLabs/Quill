<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoicePaymentRequest;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentController extends Controller
{
    public function store(InvoicePaymentRequest $request, Organization $currentOrganization, Invoice $invoice): RedirectResponse
    {
        $invoice->payments()->create($request->validated());

        return back();
    }

    public function update(InvoicePaymentRequest $request, Organization $currentOrganization, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $payment->update($request->validated());

        return back();
    }

    public function destroy(Organization $currentOrganization, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $payment->delete();

        return back();
    }
}
