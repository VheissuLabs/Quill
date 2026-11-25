<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\OrganizationMemberResource;
use App\Http\Resources\RoleCandidateResource;
use App\Http\Resources\RoleResource;
use App\Mcp\Tools\QuillTool;
use App\Models\OrganizationMembership;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Spatie\Permission\Models\Role;

#[IsReadOnly]
#[Description('Show one role: its permissions, the members holding it, and the members you could move into it with update-member-role.')]
class ShowRoleTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Role::class, $organization]);

            $role = $organization->rolesWithMembers()->findOrFail((int) $request->get('role_id'));

            return Response::json([
                'role' => RoleResource::make($role)->resolve(),
                'members' => $organization->membershipsHolding($role)->map(fn (OrganizationMembership $membership) => OrganizationMemberResource::make($membership, $user)->resolve()),
                'candidates' => RoleCandidateResource::forRole($organization, $role, $user),
            ]);
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
