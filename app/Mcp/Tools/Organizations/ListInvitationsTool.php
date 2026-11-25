<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\OrganizationInvitationResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the invitations an organization has sent that are not accepted yet, with the code cancel-invitation takes.')]
class ListInvitationsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'view', $organization);

            return Response::json(OrganizationInvitationResource::collection(
                $organization->invitations()->whereNull('accepted_at')->with('role')->get()
            )->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
