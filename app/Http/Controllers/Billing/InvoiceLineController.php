<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoiceLineRequest;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class InvoiceLineController extends Controller
{
    public function store(InvoiceLineRequest $request, Organization $currentOrganization, Invoice $invoice): RedirectResponse
    {
        $invoice->addLine($request->lineAttributes());

        return back();
    }

    public function update(InvoiceLineRequest $request, Organization $currentOrganization, Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        $line->update($request->lineAttributes());

        return back();
    }

    public function destroy(Organization $currentOrganization, Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        $line->delete();

        return back();
    }
}
