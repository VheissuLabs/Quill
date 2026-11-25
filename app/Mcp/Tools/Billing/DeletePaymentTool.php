<?php

namespace App\Mcp\Tools\Billing;

use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a recorded payment from an invoice; its balance and status are recalculated.')]
class DeletePaymentTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $payment = $invoice->payments()->findOrFail((int) $request->get('payment_id'));
            $this->authorize($request, 'update', $invoice);

            $payment->delete();

            return Response::json(InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
            'payment_id' => $schema->integer()->description('Payment id.')->required(),
        ];
    }
}
