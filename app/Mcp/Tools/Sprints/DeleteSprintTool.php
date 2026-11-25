<?php

namespace App\Mcp\Tools\Sprints;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a sprint. Its issues return to the backlog. Needs the manage sprints permission.')]
class DeleteSprintTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $sprint = $organization->allSprints()->findOrFail((int) $request->get('sprint_id'));
            $this->authorize($request, 'delete', $sprint);

            $sprint->delete();

            return Response::text(__('Sprint deleted.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'sprint_id' => $schema->integer()->description('Sprint id.')->required(),
        ];
    }
}
