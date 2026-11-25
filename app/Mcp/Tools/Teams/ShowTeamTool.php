<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Resources\BoardColumnResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserContactResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show a team: its members, the organization members who could be added, its board columns in order with their issue counts, and which modules it has switched on.')]
class ShowTeamTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'view', $team);

            return Response::json([
                'team' => TeamResource::make($team)->resolve(),
                'modules' => $team->moduleSettings(),
                'members' => UserContactResource::collection($team->members()->orderBy('name')->get())->resolve(),
                'availableMembers' => UserContactResource::collection(
                    $organization->members()->whereKeyNot($team->members()->pluck('users.id'))->orderBy('name')->get()
                )->resolve(),
                'columns' => BoardColumnResource::collection($team->columns()->withCount('issues')->get())->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug, from list-teams.')->required(),
        ];
    }
}
