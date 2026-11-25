<?php

namespace App\Mcp\Tools\Projects;

use App\Enums\ProjectVisibility;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Resources\ProjectFormResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a project. Public projects are visible to everyone with a public feedback board; private ones only to project managers and the given teams. Needs the manage projects permission. Returns the project with its id and slug.')]
class CreateProjectTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Project::class, $organization]);

            $validated = $this->formRequest(ProjectRequest::class, $request, ['current_organization' => $organization]);

            $project = $organization->projects()->make();
            $project->saveWithTeams($validated->safe()->except('team_ids'), $validated->validated('team_ids', []));

            return Response::json(ProjectFormResource::make($project)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'name' => $schema->string()->description('Project name.')->required(),
            'visibility' => $schema->string()->enum(array_column(ProjectVisibility::cases(), 'value'))->description('Who can see the project.')->required(),
            'description' => $schema->string()->description('Short description.'),
            'client_id' => $schema->integer()->description('Id of the client the project is for.'),
            'team_ids' => $schema->array()->items($schema->integer())->description('Ids of the teams that can see a private project.'),
            'billing_rate' => $schema->number()->description('Hourly billing rate. Only saved when you manage invoices.'),
        ];
    }
}
