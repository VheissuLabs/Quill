<?php

namespace App\Mcp\Tools\Feedback;

use App\Http\Resources\CommentResource;
use App\Http\Resources\FeedbackResource;
use App\Http\Resources\FeedbackViewerResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Comment;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show one feedback request with its votes, linked issue, comment threads (internal notes included) and what you are allowed to do with it.')]
class ShowFeedbackTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $feedback = Feedback::query()->inOrganization($organization)->withSummary($user)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'view', $feedback);

            return Response::json([
                ...FeedbackResource::make($feedback)->withIssue()->resolve(),
                'project' => ['id' => $feedback->project->id, 'name' => $feedback->project->name, 'slug' => $feedback->project->slug],
                'viewer' => FeedbackViewerResource::make($feedback)->for($user)->resolve(),
                'comments' => $feedback->comments()->threadsVisibleTo(true)->get()
                    ->map(fn (Comment $comment) => CommentResource::make($comment, $user)->resolve())
                    ->all(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id, from list-feedback.')->required(),
        ];
    }
}
