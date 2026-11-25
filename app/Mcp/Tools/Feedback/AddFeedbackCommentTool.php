<?php

namespace App\Mcp\Tools\Feedback;

use App\Actions\Comments\PostFeedbackComment;
use App\Http\Requests\Comments\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Comment on a feedback request, or reply in a thread with parent_id. Internal notes are seen only by organization members. A line that is a slash command runs it instead of being posted: /for, /against, /withdraw, /internal, /status <status>, /convert.')]
class AddFeedbackCommentTool extends QuillTool
{
    public function handle(Request $request, PostFeedbackComment $postComment): Response
    {
        return $this->attempt(function () use ($request, $postComment) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'comment', $feedback);

            $validated = $this->formRequest(StoreCommentRequest::class, $request, ['organization' => $organization, 'project' => $feedback->project, 'feedback' => $feedback],
                input: collect($request->all())->only(['body', 'internal', 'parent_id', 'mentions'])->all());

            $result = $postComment->handle(
                $feedback,
                $user,
                $validated->validated('body'),
                $validated->boolean('internal'),
                $validated->filled('parent_id') ? $validated->integer('parent_id') : null,
                $validated->validated('mentions', []),
            );

            return Response::json([
                'comment' => $result['comment'] ? CommentResource::make($result['comment']->load(['author', 'mentions']), $user)->resolve() : null,
                'messages' => $result['messages'],
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id.')->required(),
            'body' => $schema->string()->description('The comment, as plain text or HTML, up to 5000 characters.')->required(),
            'internal' => $schema->boolean()->description('Post as an internal note only organization members see.'),
            'parent_id' => $schema->integer()->description('Comment id to reply to, from show-feedback.'),
            'mentions' => $schema->array()->items($schema->integer())->description('User ids to notify. Only organization members and people already in the conversation.'),
        ];
    }
}
