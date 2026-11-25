<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Requests\Organizations\DeleteOrganizationMemberRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove a member from an organization. Needs the remove members permission. Only an owner can remove another owner, and never the last one. To leave yourself, use leave-organization.')]
class RemoveMemberTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $member = $organization->members()->findOrFail((int) $request->get('user_id'));

            $this->formRequest(DeleteOrganizationMemberRequest::class, $request, ['current_organization' => $organization, 'user' => $member], 'DELETE', []);

            $organization->removeMember($member);

            if ($member->isCurrentOrganization($organization)) {
                $member->switchToFallbackOrganization($organization);
            }

            return Response::text(__(':name was removed from :organization.', ['name' => $member->name, 'organization' => $organization->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'user_id' => $schema->integer()->description('User id of the member to remove; list-members shows them.')->required(),
        ];
    }
}
