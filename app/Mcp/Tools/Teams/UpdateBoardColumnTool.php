<?php

namespace App\Mcp\Tools\Teams;

use App\Enums\IssueStatus;
use App\Http\Requests\Teams\BoardColumnRequest;
use App\Http\Resources\BoardColumnResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename a board column or change its status. Send only the fields to change. Issues in the column take its new status. Needs the manage teams permission and the workflow module.')]
class UpdateBoardColumnTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $column = $team->columns()->findOrFail((int) $request->get('column_id'));
            $this->authorize($request, 'update', $column);

            $validated = $this->formRequest(BoardColumnRequest::class, $request, ['current_organization' => $organization, 'team' => $team, 'column' => $column], 'PATCH', [
                'name' => $request->get('name', $column->name),
                'status' => $request->get('status', $column->status->value),
            ]);

            $column->change($validated->validated());

            return Response::json(BoardColumnResource::make($column->loadCount('issues'))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'column_id' => $schema->integer()->description('Board column id, from show-team.')->required(),
            'name' => $schema->string()->description('Column name, up to 50 characters.'),
            'status' => $schema->string()->enum(array_map(fn (IssueStatus $status) => $status->value, IssueStatus::columnStatuses()))->description('Status issues take in this column.'),
        ];
    }
}
