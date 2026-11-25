<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\OrganizationMemberResource;
use App\Mcp\Tools\QuillTool;
use App\Models\OrganizationMembership;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the members of an organization with their user id, email and role, and whether you may change their role or remove them.')]
class ListMembersTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);
            $organization = $this->organization($request);
            $this->authorize($request, 'view', $organization);

            return Response::json($organization->membershipsWithRoles()
                ->map(fn (OrganizationMembership $membership) => OrganizationMemberResource::make($membership, $user)->resolve())
                ->sortBy('name')
                ->values());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
