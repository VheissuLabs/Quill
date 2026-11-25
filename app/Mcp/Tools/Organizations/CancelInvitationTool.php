<?php

namespace App\Mcp\Tools\Organizations;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Cancel an invitation the organization sent, so its link stops working. Needs the cancel invitations permission.')]
class CancelInvitationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $invitation = $organization->invitations()->where('code', (string) $request->get('code'))->firstOrFail();
            $this->authorize($request, 'delete', $invitation);

            $invitation->delete();

            return Response::text(__('Invitation for :email cancelled.', ['email' => $invitation->email]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'code' => $schema->string()->description('Invitation code; list-invitations shows them.')->required(),
        ];
    }
}
