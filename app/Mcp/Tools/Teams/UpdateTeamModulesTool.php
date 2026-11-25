<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Requests\Teams\UpdateTeamModulesRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Switch sprints on or off for one team. Switching them off returns the team\'s sprint work to the backlog. Needs the manage teams permission. Returns the team\'s module settings.')]
class UpdateTeamModulesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'update', $team);

            $validated = $this->formRequest(UpdateTeamModulesRequest::class, $request, ['current_organization' => $organization, 'team' => $team], 'PATCH', [
                'sprints_enabled' => $request->get('sprints_enabled'),
            ]);

            $team->updateModuleSettings($validated->validated());

            if (! $validated->boolean('sprints_enabled')) {
                $team->returnSprintWorkToBacklog();
            }

            return Response::json($team->moduleSettings());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'sprints_enabled' => $schema->boolean()->description('Whether the team runs sprints.')->required(),
        ];
    }
}
