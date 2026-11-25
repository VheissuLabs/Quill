<?php

namespace App\Mcp\Tools\Tickets;

use App\Actions\Comments\NotifyAboutComment;
use App\Actions\Tickets\ReplyToTicket;
use App\Http\Requests\Tickets\StoreTicketReplyRequest;
use App\Http\Resources\CommentResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Reply to a support ticket. A reply is emailed to the customer and marks the ticket awaiting customer; set internal to true to leave a note for the team instead, which is never emailed.')]
class ReplyToTicketTool extends QuillTool
{
    public function handle(Request $request, ReplyToTicket $reply, NotifyAboutComment $notify): Response
    {
        return $this->attempt(function () use ($request, $reply, $notify) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $ticket = $organization->tickets()->findOrFail((int) $request->get('ticket_id'));
            $this->authorize($request, 'reply', $ticket);

            $validated = $this->formRequest(StoreTicketReplyRequest::class, $request, ['current_organization' => $organization, 'ticket' => $ticket],
                input: collect($request->all())->only(['body', 'internal', 'mentions'])->all());

            $comment = $reply->handle(
                $ticket,
                $user,
                $validated->validated('body'),
                $validated->boolean('internal'),
                $validated->validated('mentions', []),
            );

            $notify->handle($comment, $user, null);

            return Response::json(CommentResource::make($comment->load(['author', 'mentions', 'emailMessage.attachments']), $user)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'ticket_id' => $schema->integer()->description('Ticket id.')->required(),
            'body' => $schema->string()->description('The reply, as plain text or HTML.')->required(),
            'internal' => $schema->boolean()->description('Keep it as an internal note instead of emailing the customer.'),
            'mentions' => $schema->array()->items($schema->integer())->description('User ids of organization members to notify.'),
        ];
    }
}
