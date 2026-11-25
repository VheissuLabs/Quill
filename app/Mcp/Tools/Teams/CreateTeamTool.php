<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Requests\Teams\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a team in an organization. It starts with the default board columns and no members; add-team-member adds people. Needs the manage teams permission.')]
class CreateTeamTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Team::class, $organization]);

            $validated = $this->formRequest(TeamRequest::class, $request, ['current_organization' => $organization]);

            $team = $organization->teams()->create($validated->validated());

            return Response::json(TeamResource::make($team)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'name' => $schema->string()->description('Team name.')->required(),
        ];
    }
}
