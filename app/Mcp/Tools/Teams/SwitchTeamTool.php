<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Resources\TeamResource;
use App\Mcp\Tools\QuillTool;
use DomainException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Make a team your current team, which decides the board and sprints that list-sprints and create-sprint use. The team must be in your current organization.')]
class SwitchTeamTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();

            if (! $this->user($request)->switchTeam($team)) {
                throw new DomainException(__('That team is not in your current organization. Switch organization first.'));
            }

            return Response::json(TeamResource::make($team)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
        ];
    }
}
