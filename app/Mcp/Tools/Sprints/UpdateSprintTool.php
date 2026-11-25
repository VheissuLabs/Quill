<?php

namespace App\Mcp\Tools\Sprints;

use App\Http\Requests\Sprints\SprintRequest;
use App\Http\Resources\SprintResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename a sprint or change its dates. Send only the fields to change. Needs the manage sprints permission.')]
class UpdateSprintTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $sprint = $organization->allSprints()->findOrFail((int) $request->get('sprint_id'));
            $this->authorize($request, 'update', $sprint);

            $validated = $this->formRequest(SprintRequest::class, $request, ['current_organization' => $organization, 'sprint' => $sprint], 'PATCH', [
                'name' => $request->get('name', $sprint->name),
                'starts_at' => $request->get('starts_at', $sprint->starts_at->toDateString()),
                'ends_at' => $request->get('ends_at', $sprint->ends_at->toDateString()),
            ]);

            $sprint->update($validated->validated());

            return Response::json(SprintResource::make($sprint->loadCount('issues'))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'sprint_id' => $schema->integer()->description('Sprint id.')->required(),
            'name' => $schema->string(),
            'starts_at' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'ends_at' => $schema->string()->format('date')->description('YYYY-MM-DD, on or after starts_at.'),
        ];
    }
}
