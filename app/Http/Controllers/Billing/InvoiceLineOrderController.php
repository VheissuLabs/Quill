<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoiceLineOrderRequest;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class InvoiceLineOrderController extends Controller
{
    public function update(InvoiceLineOrderRequest $request, Organization $currentOrganization, Invoice $invoice): RedirectResponse
    {
        DB::transaction(function () use ($request, $invoice) {
            foreach (array_values($request->validated('line_ids')) as $position => $lineId) {
                $invoice->lines()->whereKey($lineId)->toBase()->update(['sort_order' => $position]);
            }
        });

        return back();
    }
}
