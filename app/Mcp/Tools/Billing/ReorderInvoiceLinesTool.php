<?php

namespace App\Mcp\Tools\Billing;

use App\Http\Requests\Billing\InvoiceLineOrderRequest;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Reorder the lines of an unsent invoice. Pass every line id of the invoice, in the new order.')]
class ReorderInvoiceLinesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'editLines', $invoice);

            $validated = $this->formRequest(InvoiceLineOrderRequest::class, $request, ['current_organization' => $organization, 'invoice' => $invoice], 'PUT',
                collect($request->all())->only(['line_ids'])->all());

            $invoice->reorderLines($validated->validated('line_ids'));

            return Response::json(InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
            'line_ids' => $schema->array()->items($schema->integer())->description('All of the invoice\'s line ids, first to last.')->required(),
        ];
    }
}
