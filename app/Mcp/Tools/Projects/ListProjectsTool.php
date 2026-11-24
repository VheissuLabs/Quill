<?php

namespace App\Mcp\Tools\Projects;

use App\Http\Resources\ProjectListResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the projects in an organization that you can see, with each project\'s id, slug and client.')]
class ListProjectsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        $organization = $this->organization($request);
        $this->authorize($request, 'viewAny', [Project::class, $organization]);

        $projects = $organization->projects()->visibleTo($this->user($request))->with('client')->orderBy('name')->get();

        return Response::json(ProjectListResource::collection($projects)->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
