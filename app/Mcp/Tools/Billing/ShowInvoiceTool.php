<?php

namespace App\Mcp\Tools\Billing;

use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show an invoice with its lines (hours, rate and amount per task), payments and totals.')]
class ShowInvoiceTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'view', $invoice);

            return Response::json(InvoiceResource::make($invoice->load(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
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
