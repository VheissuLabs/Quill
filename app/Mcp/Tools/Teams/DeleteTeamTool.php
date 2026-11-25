<?php

namespace App\Mcp\Tools\Teams;

use App\Http\Requests\Teams\DeleteTeamRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a team. Its members, projects, sprints and issues move to target_team_id, or stay with the organization when no target is given. Needs the manage teams permission.')]
class DeleteTeamTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $team = $organization->teams()->identifiedBy((string) $request->get('team'))->firstOrFail();
            $this->authorize($request, 'delete', $team);

            $validated = $this->formRequest(DeleteTeamRequest::class, $request, ['current_organization' => $organization, 'team' => $team], 'DELETE', [
                'target_team_id' => $request->get('target_team_id'),
            ]);

            $target = $validated->filled('target_team_id')
                ? $organization->teams()->whereKey($validated->integer('target_team_id'))->firstOrFail()
                : $organization;

            $team->mergeInto($target);

            return Response::text(__('Team deleted, its work moved to ":name".', ['name' => $target->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'team' => $schema->string()->description('Team id or slug.')->required(),
            'target_team_id' => $schema->integer()->description('Id of another team in the organization to take over the work.'),
        ];
    }
}
