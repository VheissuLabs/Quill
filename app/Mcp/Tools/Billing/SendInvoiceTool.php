<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\SendInvoice;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Email an invoice to its contact, copying its cc list, with a link to view and pay it, and mark it sent. It needs a contact and at least one line and can only be sent once. Sending locks its amounts.')]
class SendInvoiceTool extends QuillTool
{
    public function handle(Request $request, SendInvoice $sendInvoice): Response
    {
        return $this->attempt(function () use ($request, $sendInvoice) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'send', $invoice);

            $sendInvoice->handle($invoice);

            return Response::json(InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
        ];
    }
}
