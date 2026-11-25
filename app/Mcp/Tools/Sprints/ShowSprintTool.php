<?php

namespace App\Mcp\Tools\Sprints;

use App\Enums\IssueStatus;
use App\Http\Resources\BoardColumnResource;
use App\Http\Resources\IssueResource;
use App\Http\Resources\SprintResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show a sprint with its board columns and every issue planned into it, in board order. Column ids are what move-sprint-issue takes.')]
class ShowSprintTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $sprint = $organization->allSprints()->findOrFail((int) $request->get('sprint_id'));
            $this->authorize($request, 'view', $sprint);

            $sprint->load('owner.columns')->loadCount([
                'issues',
                'issues as done_issues_count' => fn ($query) => $query->where('status', IssueStatus::Done),
            ])->loadSum('issues', 'points');

            $issues = $sprint->issues()
                ->with(['project', 'assignee', 'team.columns', 'boardColumn'])
                ->orderBy('position')
                ->orderBy('number')
                ->get();

            return Response::json([
                'sprint' => SprintResource::make($sprint)->resolve(),
                'columns' => BoardColumnResource::collection($sprint->columns())->resolve(),
                'issues' => IssueResource::collection($issues)->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'sprint_id' => $schema->integer()->description('Sprint id, from list-sprints.')->required(),
        ];
    }
}
