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

#[Description('Rename a role or replace its permissions. Omitted fields keep their current value; permissions, when given, replace the whole set. The Owner role cannot be changed. Needs the manage roles permission.')]
class UpdateRoleTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $role = $organization->roles()->with('permissions')->findOrFail((int) $request->get('role_id'));
            $this->authorize($request, 'update', $role);

            $validated = $this->formRequest(OrganizationRoleRequest::class, $request, ['current_organization' => $organization, 'role' => $role], 'PATCH', [
                'name' => $request->get('name', $role->name),
                'permissions' => $request->get('permissions', $role->permissions->pluck('name')->all()),
            ]);

            $organization->redefineRole($role, $validated->validated('name'), $validated->validated('permissions'));

            return Response::json(RoleResource::make($organization->rolesWithMembers()->findOrFail($role->id))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'role_id' => $schema->integer()->description('Role id; list-roles shows them.')->required(),
            'name' => $schema->string()->description('New role name.'),
            'permissions' => $schema->array()
                ->items($schema->string()->enum(array_column(OrganizationPermission::cases(), 'value')))
                ->description('The complete new set of permission values.'),
        ];
    }
}
