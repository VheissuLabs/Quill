<?php

namespace App\Mcp\Tools\Tickets;

use App\Enums\TicketStatus;
use App\Http\Requests\Tickets\TicketIndexRequest;
use App\Http\Resources\TicketResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Ticket;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List support tickets in the inbox, most recent conversation first. Without a status it shows open tickets and those awaiting the customer. Views: unassigned (no client yet), ready (the linked issue is done, so the customer can be told), mine (you reported the linked issue).')]
class ListTicketsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Ticket::class, $organization]);

            $index = $this->formRequest(TicketIndexRequest::class, $request, ['current_organization' => $organization], 'GET',
                collect($request->all())->only(['status', 'client', 'view'])->all());

            $limit = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']])['limit'] ?? 30;

            $tickets = $organization->tickets()
                ->inInbox($this->user($request), $index->filters())
                ->limit($limit)
                ->get();

            return Response::json(TicketResource::collection($tickets)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'status' => $schema->string()->enum(array_column(TicketStatus::cases(), 'value'))->description('Only tickets in this status.'),
            'client' => $schema->string()->description('Client slug, to show only that client\'s tickets.'),
            'view' => $schema->string()->enum(['unassigned', 'ready', 'mine']),
            'limit' => $schema->integer()->description('How many to return, 30 by default and at most 100.'),
        ];
    }
}
