<?php

namespace App\Mcp\Tools\Issues;

use App\Enums\IssueStatus;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Issue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List or search issues (tasks) in an organization, newest first. Filter by project, status, assignee or a search term that matches titles and issue numbers.')]
class ListIssuesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        $organization = $this->organization($request);
        $user = $this->user($request);
        $this->authorize($request, 'viewAny', [Issue::class, $organization]);

        $filters = $request->validate([
            'project' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'assigned_to_me' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $issues = Issue::query()
            ->whereIn('project_id', $organization->projects()->visibleTo($user)->select('projects.id'))
            ->searchedFor($filters)
            ->when($filters['assigned_to_me'] ?? false, fn ($query) => $query->where('assignee_id', $user->id))
            ->with(['project', 'team.columns', 'boardColumn', 'assignee'])
            ->latest('id')
            ->limit($filters['limit'] ?? 25)
            ->get();

        return Response::json(IssueResource::collection($issues)->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'project' => $schema->string()->description('Project id or slug.'),
            'status' => $schema->string()->enum(array_column(IssueStatus::cases(), 'value'))->description('Only issues in this status.'),
            'assigned_to_me' => $schema->boolean()->description('Only issues assigned to you.'),
            'search' => $schema->string()->description('Matches issue titles, or an issue number.'),
            'limit' => $schema->integer()->description('How many to return, 25 by default and at most 100.'),
        ];
    }
}
