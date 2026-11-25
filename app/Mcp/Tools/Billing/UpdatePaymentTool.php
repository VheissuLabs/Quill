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

#[Description('Correct a recorded payment. Send only the fields to change; show-invoice lists payment ids.')]
class UpdatePaymentTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $payment = $invoice->payments()->findOrFail((int) $request->get('payment_id'));
            $this->authorize($request, 'update', $invoice);

            $validated = $this->formRequest(InvoicePaymentRequest::class, $request, ['current_organization' => $organization, 'invoice' => $invoice, 'payment' => $payment], 'PATCH', [
                'amount' => $payment->amount,
                'paid_on' => $payment->paid_on->toDateString(),
                'method' => $payment->method->value,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                ...collect($request->all())->except(['organization', 'invoice_id', 'payment_id'])->all(),
            ]);

            $payment->update($validated->validated());

            return Response::json(InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
            'payment_id' => $schema->integer()->description('Payment id.')->required(),
            'amount' => $schema->number(),
            'paid_on' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'method' => $schema->string()->enum(array_column(PaymentMethod::cases(), 'value')),
            'reference' => $schema->string(),
            'notes' => $schema->string(),
        ];
    }
}
