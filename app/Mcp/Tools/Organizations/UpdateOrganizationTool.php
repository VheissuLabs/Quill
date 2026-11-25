<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Requests\Organizations\OrganizationRequest;
use App\Http\Resources\OrganizationSettingsResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename an organization. Its slug follows the new name, so use the returned slug from now on. Needs the edit organization settings permission.')]
class UpdateOrganizationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'update', $organization);

            $validated = $this->formRequest(OrganizationRequest::class, $request, ['current_organization' => $organization], 'PATCH', ['name' => $request->get('name')]);

            $organization = $organization->renameTo($validated->validated('name'));

            return Response::json(OrganizationSettingsResource::make($organization, $this->user($request))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'name' => $schema->string()->description('The new organization name.')->required(),
        ];
    }
}
