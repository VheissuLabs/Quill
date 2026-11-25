<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\DuplicateInvoice;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Copy an invoice, with its lines, into a new draft for the same client dated today. Returns the new invoice.')]
class DuplicateInvoiceTool extends QuillTool
{
    public function handle(Request $request, DuplicateInvoice $duplicateInvoice): Response
    {
        return $this->attempt(function () use ($request, $duplicateInvoice) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'duplicate', $invoice);

            $copy = $duplicateInvoice->handle($invoice);

            return Response::json(InvoiceResource::make($copy->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice to copy.')->required(),
        ];
    }
}
