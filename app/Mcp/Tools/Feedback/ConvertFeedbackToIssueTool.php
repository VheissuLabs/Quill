<?php

namespace App\Mcp\Tools\Feedback;

use App\Actions\Issues\CreateIssue;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Feedback;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Turn a feedback request into a backlog issue in its project, with the same title and description, and mark the feedback planned. Each request converts once. Returns the new issue.')]
class ConvertFeedbackToIssueTool extends QuillTool
{
    public function handle(Request $request, CreateIssue $createIssue): Response
    {
        return $this->attempt(function () use ($request, $createIssue) {
            $organization = $this->organization($request);
            $feedback = Feedback::query()->inOrganization($organization)->findOrFail((int) $request->get('feedback_id'));
            $this->authorize($request, 'convert', $feedback);

            $issue = $createIssue->handle($feedback->project, [
                'title' => $feedback->title,
                'description' => $feedback->description,
                'created_by' => $this->user($request)->getKey(),
            ], $feedback);

            return Response::json(IssueResource::make($issue->load(['project', 'team.columns', 'boardColumn', 'assignee']))->resolve());
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
