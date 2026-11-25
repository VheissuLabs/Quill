<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Requests\Organizations\CreateOrganizationInvitationRequest;
use App\Http\Resources\OrganizationInvitationResource;
use App\Mcp\Tools\QuillTool;
use App\Models\OrganizationInvitation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Email someone an invitation to join an organization with a given role. The invitation expires after three days. The Owner role cannot be given by invitation. Needs the send invitations permission.')]
class InviteMemberTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [OrganizationInvitation::class, $organization]);

            $validated = $this->formRequest(CreateOrganizationInvitationRequest::class, $request, ['current_organization' => $organization], input: [
                'email' => $request->get('email'),
                'role_id' => $request->get('role_id'),
            ]);

            $invitation = $organization->invite(
                $validated->validated('email'),
                $organization->roles()->findOrFail($validated->integer('role_id')),
                $this->user($request),
            );

            return Response::json(OrganizationInvitationResource::make($invitation->load('role'))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'email' => $schema->string()->description('Email address to invite.')->required(),
            'role_id' => $schema->integer()->description('Id of the role they get on joining; list-roles shows them.')->required(),
        ];
    }
}
