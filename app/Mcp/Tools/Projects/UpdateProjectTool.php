<?php

namespace App\Mcp\Tools\Projects;

use App\Enums\ProjectVisibility;
use App\Http\Requests\Projects\ProjectRequest;
use App\Http\Resources\ProjectFormResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update a project. Omitted fields keep their current value; team_ids, when given, replaces the whole set. Needs the manage projects permission.')]
class UpdateProjectTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $project = $this->project($organization, (string) $request->get('project'));
            $this->authorize($request, 'update', $project);

            $validated = $this->formRequest(ProjectRequest::class, $request, ['current_organization' => $organization, 'project' => $project], 'PATCH', [
                'name' => $project->name,
                'description' => $project->description,
                'visibility' => $project->visibility->value,
                'client_id' => $project->client_id,
                'team_ids' => $project->teams()->pluck('teams.id')->all(),
                'billing_rate' => $project->billing_rate,
                ...collect($request->all())->only(['name', 'description', 'visibility', 'client_id', 'team_ids', 'billing_rate'])->all(),
            ]);

            $project->saveWithTeams($validated->safe()->except('team_ids'), $validated->validated('team_ids', []));

            return Response::json(ProjectFormResource::make($project)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'project' => $schema->string()->description('Project id or slug; list-projects shows them.')->required(),
            'name' => $schema->string()->description('Project name.'),
            'visibility' => $schema->string()->enum(array_column(ProjectVisibility::cases(), 'value'))->description('Who can see the project.'),
            'description' => $schema->string()->description('Short description.'),
            'client_id' => $schema->integer()->description('Id of the client the project is for.'),
            'team_ids' => $schema->array()->items($schema->integer())->description('Ids of the teams that can see a private project.'),
            'billing_rate' => $schema->number()->description('Hourly billing rate. Only saved when you manage invoices.'),
        ];
    }
}
