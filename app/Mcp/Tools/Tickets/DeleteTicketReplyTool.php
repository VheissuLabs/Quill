<?php

namespace App\Mcp\Tools\Tickets;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete an internal note on a support ticket. Emails to or from the customer cannot be deleted.')]
class DeleteTicketReplyTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $ticket = $organization->tickets()->findOrFail((int) $request->get('ticket_id'));
            $comment = $ticket->comments()->findOrFail((int) $request->get('comment_id'));
            $this->authorize($request, 'delete', $comment);

            $comment->delete();

            return Response::text(__('Note deleted.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'ticket_id' => $schema->integer()->description('Ticket id.')->required(),
            'comment_id' => $schema->integer()->description('Id of the note, from show-ticket.')->required(),
        ];
    }
}
