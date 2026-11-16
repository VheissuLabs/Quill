<?php

namespace App\Http\Resources;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invoice */
class PublicInvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'isVoid' => $this->status === InvoiceStatus::Void,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'organizationName' => $this->organization->name,
            'organizationLogoUrl' => $this->organization->logoUrl(),
            'fromAddress' => $this->organization->billingSettings()->from_address,
            'clientName' => $this->client->name,
            'contactName' => $this->contact?->name,
            'contactEmail' => $this->contact?->email,
            'issueDate' => $this->issue_date->toDateString(),
            'dueDate' => $this->due_date->toDateString(),
            'servicePeriodStart' => $this->service_period_start?->toDateString(),
            'servicePeriodEnd' => $this->service_period_end?->toDateString(),
            'lines' => $this->lines->map(fn (InvoiceLine $line) => [
                'description' => $line->description,
                'issueReference' => $line->issue?->reference(),
                'referenceUrl' => $line->reference_url,
                'quantity' => $line->quantity,
                'unit' => $line->unit->value,
                'rate' => $line->rate,
                'amount' => $line->amount,
            ])->all(),
            'subtotal' => $this->subtotal,
            'taxRate' => $this->tax_rate,
            'taxAmount' => $this->tax_amount,
            'discountAmount' => $this->discount_amount,
            'total' => $this->total,
            'depositAmount' => $this->deposit_amount,
            'payments' => $this->payments->map(fn (InvoicePayment $payment) => [
                'amount' => $payment->amount,
                'paidOn' => $payment->paid_on->toDateString(),
                'methodLabel' => $payment->method->label(),
            ])->all(),
            'amountPaid' => $this->amount_paid,
            'amountDue' => $this->amountDue(),
            'payUrl' => route('invoices.public.pay', $this->public_token),
            'canPayOnline' => $this->canPayOnline(),
            'paymentProcessing' => $this->hasPendingSquareCheckout(),
            'paymentInstructions' => $this->status === InvoiceStatus::Paid ? null : $this->organization->billingSettings()->payment_instructions,
            'notes' => $this->notes,
            'terms' => $this->terms,
        ];
    }
}
