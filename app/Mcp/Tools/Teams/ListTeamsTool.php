<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Resources\TeamListResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the teams in an organization with their member counts, and which one is your current team. Your current team decides which board and sprints you see.')]
class ListTeamsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Team::class, $organization]);

            return Response::json([
                'teams' => TeamListResource::collection($organization->teams()->withCount('members')->orderBy('name')->get())->resolve(),
                'currentTeamId' => $this->user($request)->currentTeamFor($organization)?->id,
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
