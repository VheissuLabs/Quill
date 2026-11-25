<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Requests\Organizations\UpdateOrganizationMemberRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Give an organization member a different role. Needs the change member roles permission. Only owners can grant the Owner role or step another owner down, and an organization always keeps at least one owner. list-roles shows the role ids.')]
class UpdateMemberRoleTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $member = $organization->members()->findOrFail((int) $request->get('user_id'));

            $validated = $this->formRequest(UpdateOrganizationMemberRequest::class, $request, ['current_organization' => $organization, 'user' => $member], 'PATCH', ['role_id' => $request->get('role_id')]);

            $organization->changeMemberRole($member, $organization->roles()->findOrFail($validated->integer('role_id')));

            $membership = $organization->memberships()->where('user_id', $member->id)->with('user')->firstOrFail()->setRelation('organization', $organization);

            return Response::json(OrganizationMemberResource::make($membership, $this->user($request))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'user_id' => $schema->integer()->description('User id of the member; list-members shows them.')->required(),
            'role_id' => $schema->integer()->description('Id of the role to give them; list-roles shows them.')->required(),
        ];
    }
}
