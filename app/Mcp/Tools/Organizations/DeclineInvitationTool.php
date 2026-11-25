<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Requests\Organizations\RespondToOrganizationInvitationRequest;
use App\Mcp\Tools\QuillTool;
use App\Models\OrganizationInvitation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Decline an invitation sent to one of your verified email addresses. The invitation is deleted.')]
class DeclineInvitationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $invitation = OrganizationInvitation::query()->with('organization')->where('code', (string) $request->get('code'))->firstOrFail();

            $this->formRequest(RespondToOrganizationInvitationRequest::class, $request, ['invitation' => $invitation], 'DELETE', []);

            $invitation->delete();

            return Response::text(__('Invitation to :organization declined.', ['organization' => $invitation->organization->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Invitation code; list-pending-invitations shows them.')->required(),
        ];
    }
}
