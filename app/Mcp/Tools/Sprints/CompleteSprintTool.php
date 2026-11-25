<?php

namespace App\Mcp\Tools\Sprints;

use App\Http\Resources\SprintResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Complete an active sprint. Issues that are not done or cancelled return to the backlog. Needs the manage sprints permission.')]
class CompleteSprintTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $sprint = $organization->allSprints()->findOrFail((int) $request->get('sprint_id'));
            $this->authorize($request, 'update', $sprint);

            $sprint->complete();

            return Response::json(SprintResource::make($sprint->loadCount('issues'))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'sprint_id' => $schema->integer()->description('Sprint id of an active sprint.')->required(),
        ];
    }
}
