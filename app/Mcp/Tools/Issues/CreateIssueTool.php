<?php

namespace App\Mcp\Tools\Issues;

use App\Actions\Issues\CreateIssue;
use App\Enums\IssuePriority;
use App\Http\Requests\Issues\IssueRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Issue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create an issue (a task) in a project. It starts in the backlog, or in To do when given a sprint. Returns the new issue with its id, which log-time and update-issue take.')]
class CreateIssueTool extends QuillTool
{
    public function handle(Request $request, CreateIssue $createIssue): Response
    {
        return $this->attempt(function () use ($request, $createIssue) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Issue::class, $organization]);

            $project = $this->project($organization, (string) $request->get('project'));

            $validated = $this->formRequest(IssueRequest::class, $request, ['current_organization' => $organization], input: [
                ...collect($request->all())->except(['organization', 'project'])->all(),
                'project_id' => $project->id,
                'priority' => $request->get('priority', IssuePriority::None->value),
            ]);

            $issue = $createIssue->handle($project, $validated->issueAttributes());

            return Response::json(IssueResource::make($issue->load(['project', 'team.columns', 'boardColumn', 'assignee']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'project' => $schema->string()->description('Project id or slug.')->required(),
            'title' => $schema->string()->description('Short summary of the task.')->required(),
            'description' => $schema->string()->description('Details, in Markdown.'),
            'priority' => $schema->string()->enum(array_column(IssuePriority::cases(), 'value'))->description('Defaults to none.'),
            'points' => $schema->integer()->description('Estimate in points.'),
            'assignee_id' => $schema->integer()->description('User id of an organization member.'),
            'team_id' => $schema->integer()->description('Team id.'),
            'sprint_id' => $schema->integer()->description('Sprint id to plan the issue into.'),
        ];
    }
}
