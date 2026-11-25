<?php

namespace App\Mcp\Tools\Tickets;

use App\Http\Resources\TicketDetailResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Unlink a support ticket from the issue tracking it. The issue itself is kept.')]
class UnlinkTicketIssueTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $ticket = $organization->tickets()->findOrFail((int) $request->get('ticket_id'));
            $this->authorize($request, 'update', $ticket);

            $ticket->unlink();

            return Response::json(TicketDetailResource::make($ticket->fresh(['client', 'issue.project', 'reporter', 'creator']), $this->user($request))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'ticket_id' => $schema->integer()->description('Ticket id.')->required(),
        ];
    }
}
