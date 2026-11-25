<?php

namespace App\Mcp\Tools\Billing;

use App\Http\Requests\Billing\WorkTypeRequest;
use App\Mcp\Tools\QuillTool;
use App\Models\WorkType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add a work type that invoice lines can be tagged with. Names are unique in the organization.')]
class CreateWorkTypeTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [WorkType::class, $organization]);

            $validated = $this->formRequest(WorkTypeRequest::class, $request, ['current_organization' => $organization]);

            $workType = $organization->workTypes()->create($validated->validated());

            return Response::json($workType->refresh()->only(['id', 'name', 'color', 'is_active']));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'name' => $schema->string()->description('Work type name, e.g. Development.')->required(),
            'color' => $schema->string()->enum(WorkTypeRequest::colors()),
            'is_active' => $schema->boolean()->description('Whether it is offered for new lines. Defaults to true.'),
        ];
    }
}
