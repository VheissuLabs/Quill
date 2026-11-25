<?php

namespace App\Mcp\Tools\Billing;

use App\Http\Resources\TimeEntryResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Invoice;
use App\Models\TimeEntry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List recorded time entries, newest day first, with hours, description, link and the invoice each one is billed on. Filter by client, date range, or unbilled entries only (time recorded on a day whose invoice was already sent, or with no open invoice).')]
class ListTimeEntriesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Invoice::class, $organization]);

            $filters = $request->validate([
                'client' => ['nullable', 'string'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d'],
                'unbilled' => ['nullable', 'boolean'],
            ]);

            $client = filled($filters['client'] ?? null)
                ? $organization->clients()->identifiedBy($filters['client'])->firstOrFail()
                : null;

            $entries = TimeEntry::query()
                ->where('organization_id', $organization->id)
                ->with(['client:id,name', 'invoiceLine.invoice:id,number'])
                ->when($client, fn ($query) => $query->where('client_id', $client->id))
                ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('worked_on', '>=', $from))
                ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('worked_on', '<=', $to))
                ->when($filters['unbilled'] ?? false, fn ($query) => $query->whereNull('invoice_line_id'))
                ->orderByDesc('worked_on')
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            return Response::json([
                'totalHours' => round((float) $entries->sum('hours'), 2),
                'entries' => TimeEntryResource::collection($entries)->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.'),
            'from' => $schema->string()->format('date')->description('First day, YYYY-MM-DD.'),
            'to' => $schema->string()->format('date')->description('Last day, YYYY-MM-DD.'),
            'unbilled' => $schema->boolean()->description('Only entries not on any invoice.'),
        ];
    }
}
