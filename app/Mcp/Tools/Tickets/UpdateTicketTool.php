<?php

namespace App\Mcp\Tools\Tickets;

use App\Enums\TicketStatus;
use App\Http\Requests\Tickets\UpdateTicketRequest;
use App\Http\Resources\TicketDetailResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change a ticket\'s status, or assign it to a client (client_id null unassigns it). Send only the fields to change.')]
class UpdateTicketTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $ticket = $organization->tickets()->findOrFail((int) $request->get('ticket_id'));
            $this->authorize($request, 'update', $ticket);

            $validated = $this->formRequest(UpdateTicketRequest::class, $request, ['current_organization' => $organization, 'ticket' => $ticket], 'PATCH',
                collect($request->all())->only(['status', 'client_id'])->all());

            $ticket->applyChanges($validated->validated());

            return Response::json(TicketDetailResource::make($ticket->fresh(['client', 'issue.project', 'reporter', 'creator']), $user)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'ticket_id' => $schema->integer()->description('Ticket id.')->required(),
            'status' => $schema->string()->enum(array_column(TicketStatus::cases(), 'value')),
            'client_id' => $schema->integer()->nullable()->description('Client id, or null to unassign.'),
        ];
    }
}
