<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\CreateInvoice;
use App\Enums\AdjustmentType;
use App\Http\Requests\Billing\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a draft invoice for a client, or for a project (billed to the project\'s client). The invoice number, due date, contact, cc list and tax rate default from the client and billing settings. Add lines with add-invoice-line, then send-invoice.')]
class CreateInvoiceTool extends QuillTool
{
    public function handle(Request $request, CreateInvoice $createInvoice): Response
    {
        return $this->attempt(function () use ($request, $createInvoice) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Invoice::class, $organization]);

            $validated = $this->formRequest(StoreInvoiceRequest::class, $request, ['current_organization' => $organization]);

            $invoice = $createInvoice->handle($organization, $organization->clients()->findOrFail($validated->integer('client_id')), $validated->invoiceAttributes());

            return Response::json(InvoiceResource::make($invoice->fresh(['client', 'organization', 'lines.issue.project', 'payments']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client_id' => $schema->integer()->description('Client to bill. Required unless project_id is given.'),
            'project_id' => $schema->integer()->description('Project the invoice is for; its client is billed when client_id is omitted.'),
            'sprint_id' => $schema->integer()->description('Sprint the invoice covers.'),
            'contact_id' => $schema->integer()->description('Client contact who receives the invoice; defaults to the client\'s billing contact.'),
            'cc_emails' => $schema->array()->items($schema->string()->format('email'))->description('Addresses copied when the invoice is sent.'),
            'issue_date' => $schema->string()->format('date')->description('YYYY-MM-DD. Defaults to today.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD. Defaults to the issue date plus payment terms.'),
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
