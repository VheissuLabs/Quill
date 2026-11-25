<?php

namespace App\Mcp\Tools\Feedback;

use App\Http\Resources\FeedbackResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Withdraw your vote on a feedback request.')]
class RemoveFeedbackVoteTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'vote', $feedback);

            $feedback->clearVoteFrom($user);

            return Response::json(FeedbackResource::make(Feedback::query()->withSummary($user)->findOrFail($feedback->id))->withIssue()->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id.')->required(),
        ];
    }
}
