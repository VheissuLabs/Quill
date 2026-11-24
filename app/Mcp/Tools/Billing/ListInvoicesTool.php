<?php

namespace App\Mcp\Tools\Billing;

use App\Enums\InvoiceStatus;
use App\Http\Resources\InvoiceListResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List invoices, newest first. isCurrentPeriod marks a client\'s open billing-cycle invoice, where logged time goes.')]
class ListInvoicesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        $organization = $this->organization($request);
        $this->authorize($request, 'viewAny', [Invoice::class, $organization]);

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'client_id' => ['nullable', 'integer'],
        ]);

        $invoices = $organization->invoices()
            ->with('client:id,name,slug')
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['client_id'] ?? null, fn ($query, int $client) => $query->where('client_id', $client))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return Response::json(InvoiceListResource::collection($invoices)->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'status' => $schema->string()->enum(array_column(InvoiceStatus::cases(), 'value')),
            'client_id' => $schema->integer(),
        ];
    }
}
