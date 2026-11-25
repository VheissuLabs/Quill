<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\RoleResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Spatie\Permission\Models\Role;

#[IsReadOnly]
#[Description('List an organization\'s roles with their ids, permissions and members. Role ids are what update-member-role and invite-member take.')]
class ListRolesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [Role::class, $organization]);

            return Response::json(RoleResource::collection($organization->rolesWithMembers()->get())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
