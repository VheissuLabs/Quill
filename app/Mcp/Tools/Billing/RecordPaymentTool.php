<?php

namespace App\Mcp\Tools\Billing;

use App\Enums\PaymentMethod;
use App\Http\Requests\Billing\InvoicePaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Record a payment received against an invoice. The invoice becomes partial or paid as payments cover its total.')]
class RecordPaymentTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'update', $invoice);

            $validated = $this->formRequest(InvoicePaymentRequest::class, $request, ['current_organization' => $organization, 'invoice' => $invoice], 'POST',
                collect($request->all())->except(['organization', 'invoice_id'])->all());

            $payment = $invoice->payments()->create($validated->validated());

            return Response::json([
                'paymentId' => $payment->id,
                'invoice' => InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
            'amount' => $schema->number()->description('Amount received.')->required(),
            'paid_on' => $schema->string()->format('date')->description('YYYY-MM-DD the payment arrived.')->required(),
            'method' => $schema->string()->enum(array_column(PaymentMethod::cases(), 'value'))->required(),
            'reference' => $schema->string()->description('Check number, transaction id or similar.'),
            'notes' => $schema->string(),
        ];
    }
}
