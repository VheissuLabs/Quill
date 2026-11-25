<?php

namespace App\Mcp\Tools\Feedback;

use App\Enums\FeedbackType;
use App\Http\Requests\Feedback\FeedbackRequest;
use App\Http\Resources\FeedbackResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Edit a feedback request\'s title, description or type. Send only the fields to change. Authors can edit their own; feedback managers can edit any. Use update-feedback-status to change its status.')]
class UpdateFeedbackTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'update', $feedback);

            $validated = $this->formRequest(FeedbackRequest::class, $request, ['organization' => $organization, 'project' => $feedback->project, 'feedback' => $feedback], 'PATCH', [
                'title' => $feedback->title,
                'description' => $feedback->description,
                'type' => $feedback->type->value,
                ...collect($request->all())->only(['title', 'description', 'type'])->all(),
            ]);

            $feedback->update($validated->validated());

            return Response::json(FeedbackResource::make(Feedback::query()->withSummary($user)->findOrFail($feedback->id))->withIssue()->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'feedback_id' => $schema->integer()->description('Feedback id.')->required(),
            'title' => $schema->string()->description('Up to 255 characters.'),
            'description' => $schema->string()->description('Up to 5000 characters.'),
            'type' => $schema->string()->enum(array_column(FeedbackType::cases(), 'value')),
        ];
    }
}
