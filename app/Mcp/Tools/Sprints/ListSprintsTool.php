<?php

namespace App\Mcp\Tools\Sprints;

use App\Http\Resources\SprintResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Sprint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the sprints on your current board, newest first, with their status, dates, issue counts and points. The board belongs to your current team, or to the organization when you have no team; switch-team changes it.')]
class ListSprintsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Sprint::class, $organization]);

            $sprints = Sprint::boardFor($this->user($request)->boardOwnerFor($organization));

            return Response::json(SprintResource::collection($sprints)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
