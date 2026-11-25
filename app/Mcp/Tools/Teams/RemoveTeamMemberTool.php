<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Resources\UserContactResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove someone from a team. They stay in the organization. Needs the manage teams permission. Returns the team\'s remaining members.')]
class RemoveTeamMemberTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'update', $team);

            $member = $team->members()->findOrFail((int) $request->get('user_id'));

            $team->members()->detach($member);

            return Response::json(UserContactResource::collection($team->members()->orderBy('name')->get())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'user_id' => $schema->integer()->description('User id of a team member.')->required(),
        ];
    }
}
