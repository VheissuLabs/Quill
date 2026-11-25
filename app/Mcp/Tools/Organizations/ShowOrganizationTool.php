<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\OrganizationSettingsResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show an organization\'s settings: its name, slug, whether you are an owner, which product modules and capabilities are switched on, and the sidebar order of the modules.')]
class ShowOrganizationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'view', $organization);

            return Response::json(OrganizationSettingsResource::make($organization, $this->user($request))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
