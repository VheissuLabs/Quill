<?php

namespace App\Mcp\Tools\Billing;

use App\Http\Requests\Billing\WorkTypeRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename, recolor, or activate or deactivate a work type. Send only the fields to change.')]
class UpdateWorkTypeTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $workType = $organization->workTypes()->findOrFail((int) $request->get('work_type_id'));
            $this->authorize($request, 'update', $workType);

            $validated = $this->formRequest(WorkTypeRequest::class, $request, ['current_organization' => $organization, 'workType' => $workType], 'PATCH', [
                'name' => $workType->name,
                ...collect($request->all())->only(['name', 'color', 'is_active'])->all(),
            ]);

            $workType->update($validated->validated());

            return Response::json($workType->only(['id', 'name', 'color', 'is_active']));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'work_type_id' => $schema->integer()->description('Work type id; list-work-types shows them.')->required(),
            'name' => $schema->string(),
            'color' => $schema->string()->enum(WorkTypeRequest::colors()),
            'is_active' => $schema->boolean()->description('Whether it is offered for new lines.'),
        ];
    }
}
