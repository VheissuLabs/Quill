<?php

namespace App\Mcp\Tools\Projects;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a project permanently. Needs the manage projects permission. Confirm with the user first.')]
class DeleteProjectTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $project = $this->project($organization, (string) $request->get('project'));
            $this->authorize($request, 'delete', $project);

            $project->delete();

            return Response::text(__('Project ":name" deleted.', ['name' => $project->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'project' => $schema->string()->description('Project id or slug; list-projects shows them.')->required(),
        ];
    }
}
