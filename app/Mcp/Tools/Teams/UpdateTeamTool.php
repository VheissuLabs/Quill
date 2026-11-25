<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Requests\Teams\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename a team. Needs the manage teams permission.')]
class UpdateTeamTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'update', $team);

            $validated = $this->formRequest(TeamRequest::class, $request, ['current_organization' => $organization, 'team' => $team], 'PATCH', [
                'name' => $request->get('name'),
            ]);

            $team->update($validated->validated());

            return Response::json(TeamResource::make($team)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'name' => $schema->string()->description('New name.')->required(),
        ];
    }
}
