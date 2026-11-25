<?php

namespace App\Mcp\Tools\Feedback;

use App\Enums\FeedbackStatus;
use App\Http\Requests\Feedback\UpdateFeedbackStatusRequest;
use App\Http\Resources\FeedbackResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Move a feedback request to another status. Completed and declined requests stop taking votes. Needs the manage feedback permission.')]
class UpdateFeedbackStatusTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'updateStatus', $feedback);

            $validated = $this->formRequest(UpdateFeedbackStatusRequest::class, $request, ['organization' => $organization, 'project' => $feedback->project, 'feedback' => $feedback], 'PATCH',
                collect($request->all())->only('status')->all());

            $feedback->update(['status' => FeedbackStatus::from($validated->validated('status'))]);

            return Response::json(FeedbackResource::make(Feedback::query()->withSummary($user)->findOrFail($feedback->id))->withIssue()->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id.')->required(),
            'status' => $schema->string()->enum(array_column(FeedbackStatus::cases(), 'value'))->required(),
        ];
    }
}
