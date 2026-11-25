<?php

namespace App\Mcp\Tools\Organizations;

use App\Actions\Organizations\DeleteOrganizationRole;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a role nobody holds. Move its members to another role with update-member-role first. The Owner role cannot be deleted. Needs the manage roles permission.')]
class DeleteRoleTool extends QuillTool
{
    public function handle(Request $request, DeleteOrganizationRole $deleteOrganizationRole): Response
    {
        return $this->attempt(function () use ($request, $deleteOrganizationRole) {
            $organization = $this->organization($request);
            $role = $organization->roles()->findOrFail((int) $request->get('role_id'));
            $this->authorize($request, 'delete', $role);

            $deleteOrganizationRole->handle($role);

            return Response::text(__('Role ":name" deleted.', ['name' => $role->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'role_id' => $schema->integer()->description('Role id; list-roles shows them.')->required(),
        ];
    }
}
