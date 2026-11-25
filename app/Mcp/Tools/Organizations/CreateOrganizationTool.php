<?php

namespace App\Mcp\Tools\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Http\Requests\Organizations\OrganizationRequest;
use App\Http\Resources\UserOrganizationResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Organization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a new organization. You become its owner and it becomes your current organization. Returns the organization with its slug.')]
class CreateOrganizationTool extends QuillTool
{
    public function handle(Request $request, CreateOrganization $createOrganization): Response
    {
        return $this->attempt(function () use ($request, $createOrganization) {
            $user = $this->user($request);
            $this->authorize($request, 'create', Organization::class);

            $validated = $this->formRequest(OrganizationRequest::class, $request, [], input: ['name' => $request->get('name')]);

            $organization = $createOrganization->handle($user, $validated->validated('name'));

            return Response::json(UserOrganizationResource::make($organization, $user)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name of the new organization.')->required(),
        ];
    }
}
