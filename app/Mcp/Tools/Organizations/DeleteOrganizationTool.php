<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Requests\Organizations\DeleteOrganizationRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete an organization with all of its projects, members and invitations. This cannot be undone. Needs the delete organization permission, and the exact organization name as confirmation. Always confirm with the user first.')]
class DeleteOrganizationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);
            $organization = $this->organization($request);

            $this->formRequest(DeleteOrganizationRequest::class, $request, ['current_organization' => $organization], 'DELETE', ['name' => $request->get('confirm_name')]);

            $organization->deleteWith($user);

            if ($user->isCurrentOrganization($organization)) {
                $user->switchToFallbackOrganization($organization);
            }

            return Response::text(__('Organization ":name" deleted.', ['name' => $organization->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema)->required(),
            'confirm_name' => $schema->string()->description('The organization\'s exact name, to confirm the deletion.')->required(),
        ];
    }
}
