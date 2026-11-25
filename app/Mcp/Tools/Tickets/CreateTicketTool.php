<?php

namespace App\Mcp\Tools\Tickets;

use App\Actions\Tickets\OpenTicket;
use App\Http\Requests\Tickets\StoreTicketRequest;
use App\Http\Resources\TicketDetailResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Ticket;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Open a support ticket on a customer\'s behalf. Pick an existing contact (list-client-contacts) or give a name and email; with a client_id and a new email the person is added as that client\'s contact. The body is kept as an internal note unless send is true, which emails it to the customer.')]
class CreateTicketTool extends QuillTool
{
    public function handle(Request $request, OpenTicket $openTicket): Response
    {
        return $this->attempt(function () use ($request, $openTicket) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $this->authorize($request, 'create', [Ticket::class, $organization]);

            $validated = $this->formRequest(StoreTicketRequest::class, $request, ['current_organization' => $organization]);

            $ticket = $openTicket->handle($organization, $user, $validated->ticketAttributes());

            return Response::json(TicketDetailResource::make($ticket->load(['client', 'issue.project', 'reporter', 'creator']), $user)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client_contact_id' => $schema->integer()->description('Existing contact id, from list-client-contacts. Replaces requester_name and requester_email.'),
            'client_id' => $schema->integer()->description('Client the ticket belongs to.'),
            'requester_name' => $schema->string()->description('Customer name, when not picking a contact.'),
            'requester_email' => $schema->string()->description('Customer email, when not picking a contact.'),
            'subject' => $schema->string()->description('Up to 250 characters.')->required(),
            'body' => $schema->string()->description('The first message, as plain text or HTML.')->required(),
            'send' => $schema->boolean()->description('Email the body to the customer. Defaults to false, which keeps it as an internal note.'),
        ];
    }
}
