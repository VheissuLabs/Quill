<?php

namespace App\Mcp\Tools\Teams;

use App\Enums\IssueStatus;
use App\Http\Requests\Teams\BoardColumnRequest;
use App\Http\Resources\BoardColumnResource;
use App\Mcp\Tools\QuillTool;
use App\Models\BoardColumn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add a column to the end of a team\'s board. Issues moved onto it take its status. Needs the manage teams permission and the workflow module. Returns the team\'s columns in order.')]
class CreateBoardColumnTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'create', [BoardColumn::class, $team]);

            $validated = $this->formRequest(BoardColumnRequest::class, $request, ['current_organization' => $organization, 'team' => $team], input: [
                'name' => $request->get('name'),
                'status' => $request->get('status'),
            ]);

            $team->addColumn($validated->validated());

            return Response::json(BoardColumnResource::collection($team->columns()->withCount('issues')->get())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'name' => $schema->string()->description('Column name, up to 50 characters.')->required(),
            'status' => $schema->string()->enum(array_map(fn (IssueStatus $status) => $status->value, IssueStatus::columnStatuses()))->description('Status issues take in this column.')->required(),
        ];
    }
}
