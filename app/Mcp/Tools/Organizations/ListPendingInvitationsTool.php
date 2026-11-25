<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\PendingInvitationResource;
use App\Mcp\Tools\QuillTool;
use App\Models\OrganizationInvitation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the open invitations sent to you to join other organizations, with the code accept-invitation and decline-invitation take.')]
class ListPendingInvitationsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            return Response::json(PendingInvitationResource::collection(OrganizationInvitation::pendingFor($this->user($request))->get())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
