<?php

namespace App\Mcp\Tools\Billing;

use App\Enums\LineUnit;
use App\Http\Requests\Billing\InvoiceLineRequest;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change an invoice line. Send only the fields to change. On a sent invoice only description, work type and reference URL can change.')]
class UpdateInvoiceLineTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $line = $invoice->lines()->findOrFail((int) $request->get('line_id'));
            $this->authorize($request, 'update', $invoice);

            $validated = $this->formRequest(InvoiceLineRequest::class, $request, ['current_organization' => $organization, 'invoice' => $invoice, 'line' => $line], 'PATCH',
                collect($request->all())->except(['organization', 'invoice_id', 'line_id'])->all());

            $line->update($validated->lineAttributes());

            return Response::json(InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
            'line_id' => $schema->integer()->description('Line id; show-invoice lists them.')->required(),
            'description' => $schema->string(),
            'quantity' => $schema->number()->description('Hours or units.'),
            'unit' => $schema->string()->enum(array_column(LineUnit::cases(), 'value')),
            'rate' => $schema->number()->description('Price per unit.'),
            'work_type_id' => $schema->integer()->description('Work type id, or null to clear.'),
            'issue_id' => $schema->integer()->description('Issue id, or null to unlink.'),
            'reference_url' => $schema->string()->format('uri')->description('Link to the work, or null to clear.'),
        ];
    }
}
