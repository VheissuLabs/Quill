<?php

namespace App\Mcp\Tools\Billing;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a work type. Lines tagged with it lose the tag; to keep history, deactivate it with update-work-type instead.')]
class DeleteWorkTypeTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $workType = $organization->workTypes()->findOrFail((int) $request->get('work_type_id'));
            $this->authorize($request, 'delete', $workType);

            $workType->delete();

            return Response::text(__('Deleted work type :name.', ['name' => $workType->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'work_type_id' => $schema->integer()->description('Work type id.')->required(),
        ];
    }
}
