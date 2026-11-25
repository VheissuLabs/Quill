<?php

namespace App\Mcp\Tools\Billing;

use App\Enums\AdjustmentType;
use App\Http\Requests\Billing\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change an invoice\'s details. Send only the fields to change. Once an invoice is sent, its tax, discount and deposit can no longer change. Void invoices cannot be changed.')]
class UpdateInvoiceTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'update', $invoice);

            $validated = $this->formRequest(UpdateInvoiceRequest::class, $request, ['current_organization' => $organization, 'invoice' => $invoice], 'PATCH',
                collect($request->all())->except(['organization', 'invoice_id'])->all());

            $invoice->update($validated->validated());
            $invoice->recalculate();

            return Response::json(InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_id' => $schema->integer()->description('Invoice id.')->required(),
            'project_id' => $schema->integer()->description('Project the invoice is for; null to clear.'),
            'sprint_id' => $schema->integer()->description('Sprint the invoice covers; null to clear.'),
            'contact_id' => $schema->integer()->description('Client contact who receives the invoice.'),
            'cc_emails' => $schema->array()->items($schema->string()->format('email'))->description('Addresses copied when the invoice is sent.'),
            'issue_date' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'service_period_start' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'service_period_end' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'tax_rate' => $schema->number()->description('Tax percentage, 0-100.'),
            'discount_type' => $schema->string()->enum(array_column(AdjustmentType::cases(), 'value')),
            'discount_value' => $schema->number()->description('Percentage or fixed amount, per discount_type.'),
            'deposit_type' => $schema->string()->enum(array_column(AdjustmentType::cases(), 'value')),
            'deposit_value' => $schema->number()->description('Percentage or fixed amount, per deposit_type.'),
        ];
    }
}
