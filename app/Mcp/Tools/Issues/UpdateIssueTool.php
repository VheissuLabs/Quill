<?php

namespace App\Mcp\Tools\Issues;

use App\Actions\Issues\UpdateIssue;
use App\Data\IssueChangesData;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Http\Requests\Issues\IssueRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change an issue. Send only the fields to change. Move it with exactly one of status (backlog, done or cancelled), board_column_id, or sprint_id.')]
class UpdateIssueTool extends QuillTool
{
    public function handle(Request $request, UpdateIssue $updateIssue): Response
    {
        return $this->attempt(function () use ($request, $updateIssue) {
            $organization = $this->organization($request);
            $issue = $this->issue($organization, (int) $request->get('issue_id'));
            $this->authorize($request, 'update', $issue);

            $validated = $this->formRequest(IssueRequest::class, $request, ['current_organization' => $organization, 'issue' => $issue], 'PATCH',
                collect($request->all())->except(['organization', 'issue_id'])->all());

            $updateIssue->handle($issue, IssueChangesData::from($validated->safe()));

            return Response::json(IssueResource::make($issue->fresh(['project', 'team.columns', 'boardColumn', 'assignee']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'issue_id' => $schema->integer()->description('Issue id.')->required(),
            'title' => $schema->string(),
            'description' => $schema->string()->description('Markdown.'),
            'priority' => $schema->string()->enum(array_column(IssuePriority::cases(), 'value')),
            'points' => $schema->integer(),
            'assignee_id' => $schema->integer()->description('User id, or null to unassign.'),
            'team_id' => $schema->integer(),
            'status' => $schema->string()->enum([IssueStatus::Backlog->value, IssueStatus::Done->value, IssueStatus::Cancelled->value]),
            'board_column_id' => $schema->integer()->description('Move onto this board column.'),
            'sprint_id' => $schema->integer()->description('Plan into this sprint.'),
        ];
    }
}
