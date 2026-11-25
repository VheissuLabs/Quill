<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\DeleteInvoice;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a draft invoice that has no payments. Sent invoices cannot be deleted; void them with void-invoice instead.')]
class DeleteInvoiceTool extends QuillTool
{
    public function handle(Request $request, DeleteInvoice $deleteInvoice): Response
    {
        return $this->attempt(function () use ($request, $deleteInvoice) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'delete', $invoice);

            $deleteInvoice->handle($invoice);

            return Response::text(__('Deleted invoice :number.', ['number' => $invoice->number]));
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
