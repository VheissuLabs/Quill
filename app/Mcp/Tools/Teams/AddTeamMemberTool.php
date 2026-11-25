<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Requests\Teams\AddTeamMemberRequest;
use App\Http\Resources\UserContactResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add an organization member to a team. show-team lists who can be added. Needs the manage teams permission. Returns the team\'s members.')]
class AddTeamMemberTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'update', $team);

            $validated = $this->formRequest(AddTeamMemberRequest::class, $request, ['current_organization' => $organization, 'team' => $team], input: [
                'user_id' => $request->get('user_id'),
            ]);

            $team->members()->attach($validated->validated('user_id'));

            return Response::json(UserContactResource::collection($team->members()->orderBy('name')->get())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'user_id' => $schema->integer()->description('User id of an organization member.')->required(),
        ];
    }
}
