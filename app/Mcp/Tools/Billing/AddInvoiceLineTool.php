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

#[Description('Add a line to an unsent invoice. The amount is quantity times rate. Rate defaults to the project, client or organization rate; description defaults to the linked issue\'s title. To log hours on an issue, prefer log-time.')]
class AddInvoiceLineTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'editLines', $invoice);

            $validated = $this->formRequest(InvoiceLineRequest::class, $request, ['current_organization' => $organization, 'invoice' => $invoice], 'POST',
                collect($request->all())->except(['organization', 'invoice_id'])->all());

            $line = $invoice->addLine($validated->lineAttributes());

            return Response::json([
                'lineId' => $line->id,
                'invoice' => InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
            'completed_at' => $schema->string()->format('date')->description('Day the work was done, YYYY-MM-DD. Lines are grouped by day on the invoice; leave empty for undated lines.'),
            'description' => $schema->string()->description('What the line is for. Required unless issue_id is given.'),
            'quantity' => $schema->number()->description('Hours or units.')->required(),
            'unit' => $schema->string()->enum(array_column(LineUnit::cases(), 'value'))->description('Defaults to hours.'),
            'rate' => $schema->number()->description('Price per unit. Required when no default rate is set.'),
            'work_type_id' => $schema->integer()->description('Work type id; list-work-types shows them.'),
            'issue_id' => $schema->integer()->description('Issue the work was on, from a project billed to this invoice\'s client.'),
            'reference_url' => $schema->string()->format('uri')->description('Link to the work, such as a pull request.'),
        ];
    }
}
