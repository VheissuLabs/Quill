<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ReconcileSquareOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ShowPublicInvoiceRequest;
use App\Http\Resources\PublicInvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Inertia;
use Inertia\Response;

class PublicInvoiceController extends Controller
{
    public function show(ShowPublicInvoiceRequest $request, Invoice $invoice, ReconcileSquareOrder $reconcileOrder): Response
    {
        throw_if($invoice->sent_at === null, ModelNotFoundException::class);

        if ($request->squareOrderId() !== null) {
            $reconcileOrder->handle($invoice, $request->squareOrderId());
            $invoice->refresh();
        }

        return Inertia::render('invoices/Public', [
            'invoice' => PublicInvoiceResource::make($invoice->load(['organization', 'client', 'contact', 'lines.issue.project', 'payments'])),
        ]);
    }
}
