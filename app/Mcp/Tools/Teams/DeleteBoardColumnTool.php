<?php

namespace App\Mcp\Tools\Teams;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove a column from a team\'s board. Its issues move to the column before it, or after it when it is first. A board keeps at least one column. Needs the manage teams permission and the workflow module.')]
class DeleteBoardColumnTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $column = $team->columns()->findOrFail((int) $request->get('column_id'));
            $this->authorize($request, 'delete', $column);

            $fallback = $column->deleteAndReassignIssues();

            return Response::text(__('Column removed. Its issues moved to :name.', ['name' => $fallback->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'column_id' => $schema->integer()->description('Board column id, from show-team.')->required(),
        ];
    }
}
