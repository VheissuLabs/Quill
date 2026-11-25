<?php

namespace App\Mcp\Tools\Organizations;

use App\Enums\OrganizationPermission;
use App\Http\Requests\Organizations\OrganizationRoleRequest;
use App\Http\Resources\RoleResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Spatie\Permission\Models\Role;

#[Description('Create a role with a set of permissions. Needs the manage roles permission. list-permissions shows the permission values.')]
class CreateRoleTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Role::class, $organization]);

            $validated = $this->formRequest(OrganizationRoleRequest::class, $request, ['current_organization' => $organization], input: [
                'name' => $request->get('name'),
                'permissions' => $request->get('permissions', []),
            ]);

            $role = $organization->defineRole($validated->validated('name'), $validated->validated('permissions'));

            return Response::json(RoleResource::make($organization->rolesWithMembers()->findOrFail($role->id))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'name' => $schema->string()->description('Role name, unique within the organization.')->required(),
            'permissions' => $schema->array()
                ->items($schema->string()->enum(array_column(OrganizationPermission::cases(), 'value')))
                ->description('Permission values the role grants. Defaults to none.'),
        ];
    }
}
