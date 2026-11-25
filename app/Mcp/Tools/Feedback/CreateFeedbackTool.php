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

#[Description('Post a feature request or bug report on a project\'s portal board. It starts open, with your vote for it.')]
class CreateFeedbackTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $project = $this->project($organization, (string) $request->get('project'));
            $this->authorize($request, 'create', [Feedback::class, $project]);

            $validated = $this->formRequest(FeedbackRequest::class, $request, ['organization' => $organization, 'project' => $project], input: [
                ...collect($request->all())->except(['organization', 'project'])->all(),
                'type' => $request->get('type', FeedbackType::Feature->value),
            ]);

            $feedback = Feedback::submit($project, $user, $validated->validated());

            return Response::json(FeedbackResource::make(Feedback::query()->withSummary($user)->findOrFail($feedback->id))->withIssue()->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'project' => $schema->string()->description('Project id or slug.')->required(),
            'title' => $schema->string()->description('Short summary, up to 255 characters.')->required(),
            'description' => $schema->string()->description('Details, up to 5000 characters.'),
            'type' => $schema->string()->enum(array_column(FeedbackType::cases(), 'value'))->description('feature (the default) or bug.'),
        ];
    }
}
