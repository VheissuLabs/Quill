<?php

namespace App\Mcp\Tools\Feedback;

use App\Enums\VoteDirection;
use App\Http\Requests\Feedback\CastVoteRequest;
use App\Http\Resources\FeedbackResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Vote for (up) or against (down) a feedback request. Voting again replaces your earlier vote. Settled requests (completed or declined) take no votes.')]
class VoteOnFeedbackTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'vote', $feedback);

            $vote = $this->formRequest(CastVoteRequest::class, $request, ['organization' => $organization, 'project' => $feedback->project, 'feedback' => $feedback],
                input: collect($request->all())->only('direction')->all());

            $feedback->castVote($user, $vote->direction());

            return Response::json(FeedbackResource::make(Feedback::query()->withSummary($user)->findOrFail($feedback->id))->withIssue()->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id.')->required(),
            'direction' => $schema->string()->enum(array_column(VoteDirection::cases(), 'value'))->description('up (the default) or down.'),
        ];
    }
}
