<?php

namespace App\Mcp\Tools\Projects;

use App\Http\Resources\ProjectFormResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show a project: its name, slug, description, visibility, client, the teams that can see it when private, and its billing rate when you manage invoices.')]
class ShowProjectTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $project = $this->project($organization, (string) $request->get('project'));
            $this->authorize($request, 'view', $project);

            return Response::json([
                ...ProjectFormResource::make($project)->resolve(),
                'clientName' => $project->client?->name,
                'billingRate' => $organization->allowsInvoiceManagementBy($this->user($request)) ? $project->billing_rate : null,
            ]);
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
