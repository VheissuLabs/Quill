<?php

namespace App\Mcp\Tools\Sprints;

use App\Enums\SprintStatus;
use App\Http\Requests\Sprints\SprintRequest;
use App\Http\Resources\SprintResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Sprint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Plan a new sprint on your current board, the board of your current team or the organization when you have no team (switch-team changes it). It starts as planned; start-sprint makes it active. Needs the manage sprints permission.')]
class CreateSprintTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Sprint::class, $organization]);

            $validated = $this->formRequest(SprintRequest::class, $request, ['current_organization' => $organization]);

            $sprint = $this->user($request)->boardOwnerFor($organization)->sprints()->create([
                ...$validated->validated(),
                'status' => SprintStatus::Planned,
            ]);

            return Response::json(SprintResource::make($sprint->loadCount('issues'))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'name' => $schema->string()->description('Sprint name, such as "Sprint 12".')->required(),
            'starts_at' => $schema->string()->format('date')->description('First day, YYYY-MM-DD.')->required(),
            'ends_at' => $schema->string()->format('date')->description('Last day, YYYY-MM-DD, on or after starts_at.')->required(),
        ];
    }
}
