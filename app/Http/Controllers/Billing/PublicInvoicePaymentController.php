<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\StartSquareCheckout;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\SquareException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicInvoicePaymentController extends Controller
{
    public function store(Invoice $invoice, StartSquareCheckout $startCheckout): Response
    {
        try {
            $checkout = $startCheckout->handle($invoice);
        } catch (SquareException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __("Online payment isn't available right now. Please try again or use another way to pay."),
            ]);

            return to_route('invoices.public', $invoice->public_token);
        }

        return Inertia::location($checkout->url);
    }
}
