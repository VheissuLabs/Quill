<?php

namespace App\Mcp\Tools\Feedback;

use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a comment on a feedback request. Authors can delete their own; feedback managers can delete any.')]
class DeleteFeedbackCommentTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $comment = $feedback->comments()->findOrFail((int) $request->get('comment_id'));
            $this->authorize($request, 'delete', $comment);

            $comment->delete();

            return Response::text(__('Comment deleted.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id.')->required(),
            'comment_id' => $schema->integer()->description('Comment id, from show-feedback.')->required(),
        ];
    }
}
