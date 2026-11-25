<?php

namespace App\Mcp\Tools\Billing;

use App\Actions\Billing\VoidInvoice;
use App\Http\Resources\InvoiceResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Void a sent invoice that is not fully paid. It stays on record as void and can no longer be paid or changed. duplicate-invoice reissues it as a new draft.')]
class VoidInvoiceTool extends QuillTool
{
    public function handle(Request $request, VoidInvoice $voidInvoice): Response
    {
        return $this->attempt(function () use ($request, $voidInvoice) {
            $organization = $this->organization($request);
            $invoice = $organization->invoices()->findOrFail((int) $request->get('invoice_id'));
            $this->authorize($request, 'void', $invoice);

            $voidInvoice->handle($invoice);

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
