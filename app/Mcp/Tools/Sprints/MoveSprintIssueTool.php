<?php

namespace App\Mcp\Tools\Sprints;

use App\Http\Requests\Sprints\UpdateSprintColumnRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Move an issue onto a column of a sprint board, as dragging a card does. It is planned into the sprint if it is not already, takes the column\'s status, and lands at position (0 is the top) or at the bottom. Column ids come from show-sprint.')]
class MoveSprintIssueTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $sprint = $organization->allSprints()->findOrFail((int) $request->get('sprint_id'));
            $this->authorize($request, 'view', $sprint);

            $column = $sprint->owner->columns()->findOrFail((int) $request->get('column_id'));
            $issue = $this->issue($organization, (int) $request->get('issue_id'));

            $request->validate(['position' => ['nullable', 'integer', 'min:0']]);

            $issueIds = $sprint->issues()
                ->where('board_column_id', $column->id)
                ->whereKeyNot($issue->id)
                ->orderBy('position')
                ->orderBy('number')
                ->pluck('id')
                ->all();

            array_splice($issueIds, min((int) $request->get('position', count($issueIds)), count($issueIds)), 0, [$issue->id]);

            $validated = $this->formRequest(UpdateSprintColumnRequest::class, $request, ['current_organization' => $organization, 'sprint' => $sprint, 'column' => $column], 'PUT', ['issue_ids' => $issueIds]);

            $sprint->arrangeColumn($column, array_map('intval', $validated->validated('issue_ids')));

            return Response::json(IssueResource::make($issue->fresh(['project', 'team.columns', 'boardColumn', 'assignee']))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'sprint_id' => $schema->integer()->description('Sprint id.')->required(),
            'column_id' => $schema->integer()->description('Board column id, from show-sprint.')->required(),
            'issue_id' => $schema->integer()->description('Issue id to move.')->required(),
            'position' => $schema->integer()->description('Place in the column, 0 for the top. Defaults to the bottom.'),
        ];
    }
}
