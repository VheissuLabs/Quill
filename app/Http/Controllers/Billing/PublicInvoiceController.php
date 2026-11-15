<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicInvoiceResource;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Inertia;
use Inertia\Response;

class PublicInvoiceController extends Controller
{
    public function show(Invoice $invoice): Response
    {
        throw_if($invoice->sent_at === null, ModelNotFoundException::class);

        return Inertia::render('invoices/Public', [
            'invoice' => PublicInvoiceResource::make($invoice->load(['organization', 'client', 'contact', 'lines.issue.project', 'payments'])),
        ]);
    }
}
