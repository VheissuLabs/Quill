<?php

namespace App\Mcp\Tools\Tickets;

use App\Http\Resources\CommentResource;
use App\Http\Resources\TicketDetailResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Comment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show a support ticket with its conversation, newest message first: customer emails, replies, internal notes, delivery status and attachment names and sizes.')]
class ShowTicketTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $ticket = $organization->tickets()->findOrFail((int) $request->get('ticket_id'));
            $this->authorize($request, 'view', $ticket);

            $ticket->load(['client', 'issue.project', 'reporter', 'creator']);

            return Response::json([
                ...TicketDetailResource::make($ticket, $user)->resolve(),
                'replies' => $ticket->comments()->threadsVisibleTo(true, true)->get()
                    ->map(fn (Comment $comment) => CommentResource::make($comment, $user)->resolve())
                    ->all(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'ticket_id' => $schema->integer()->description('Ticket id, from list-tickets.')->required(),
        ];
    }
}
