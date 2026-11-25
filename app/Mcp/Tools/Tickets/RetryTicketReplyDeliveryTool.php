<?php

namespace App\Mcp\Tools\Tickets;

use App\Actions\Tickets\ReplyToTicket;
use App\Http\Requests\Tickets\RetryTicketReplyRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Email a ticket reply to the customer again after its delivery failed. Only replies whose delivery status is failed can be retried.')]
class RetryTicketReplyDeliveryTool extends QuillTool
{
    public function handle(Request $request, ReplyToTicket $reply): Response
    {
        return $this->attempt(function () use ($request, $reply) {
            $organization = $this->organization($request);
            $ticket = $organization->tickets()->findOrFail((int) $request->get('ticket_id'));
            $comment = $ticket->comments()->findOrFail((int) $request->get('comment_id'));
            $this->authorize($request, 'reply', $ticket);

            $this->formRequest(RetryTicketReplyRequest::class, $request, ['current_organization' => $organization, 'ticket' => $ticket, 'comment' => $comment], input: []);

            $reply->send($ticket, $comment);

            return Response::text(__('Sending the reply to :email again.', ['email' => $ticket->requester_email]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'ticket_id' => $schema->integer()->description('Ticket id.')->required(),
            'comment_id' => $schema->integer()->description('Id of the failed reply, from show-ticket.')->required(),
        ];
    }
}
