<?php

namespace App\Mcp\Tools\Organizations;

use App\Enums\SidebarSection;
use App\Http\Requests\Organizations\UpdateModuleOrderRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Set the order of the product modules in the sidebar. Pass every section exactly once, in the order to show them. Owners only.')]
class UpdateModuleOrderTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'manageModules', $organization);

            $validated = $this->formRequest(UpdateModuleOrderRequest::class, $request, ['current_organization' => $organization], 'PATCH', ['order' => $request->get('order')]);

            $organization->update(['module_order' => $validated->validated('order')]);

            return Response::json(['moduleOrder' => $organization->moduleOrder()]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'order' => $schema->array()
                ->items($schema->string()->enum(SidebarSection::defaultOrder()))
                ->description('Every sidebar section once, in display order: '.implode(', ', SidebarSection::defaultOrder()).'.')
                ->required(),
        ];
    }
}
