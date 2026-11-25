<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoiceLineOrderRequest;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class InvoiceLineOrderController extends Controller
{
    public function update(InvoiceLineOrderRequest $request, Organization $currentOrganization, Invoice $invoice): RedirectResponse
    {
        $invoice->reorderLines($request->validated('line_ids'));

        return back();
    }
}
