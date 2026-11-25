<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Requests\Teams\UpdateBoardColumnOrderRequest;
use App\Http\Resources\BoardColumnResource;
use App\Mcp\Tools\QuillTool;
use App\Models\BoardColumn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Set the order of a team\'s board columns, left to right. Pass every column id of the team exactly once. Needs the manage teams permission and the workflow module.')]
class ReorderBoardColumnsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'create', [BoardColumn::class, $team]);

            $validated = $this->formRequest(UpdateBoardColumnOrderRequest::class, $request, ['current_organization' => $organization, 'team' => $team], 'PUT', [
                'column_ids' => $request->get('column_ids'),
            ]);

            $team->reorderColumns(array_map('intval', $validated->validated('column_ids')));

            return Response::json(BoardColumnResource::collection($team->columns()->withCount('issues')->get())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'column_ids' => $schema->array()->items($schema->integer())->description('Every column id of the team, left to right.')->required(),
        ];
    }
}
