<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Requests\Organizations\RespondToOrganizationInvitationRequest;
use App\Http\Resources\UserOrganizationResource;
use App\Mcp\Tools\QuillTool;
use App\Models\OrganizationInvitation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Accept an invitation sent to one of your verified email addresses. You join the organization with the invited role and it becomes your current organization.')]
class AcceptInvitationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);
            $invitation = OrganizationInvitation::query()->where('code', (string) $request->get('code'))->firstOrFail();

            $this->formRequest(RespondToOrganizationInvitationRequest::class, $request, ['invitation' => $invitation], input: []);

            $invitation->acceptFor($user);

            return Response::json(UserOrganizationResource::make($invitation->organization, $user)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Invitation code; list-pending-invitations shows them.')->required(),
        ];
    }
}
