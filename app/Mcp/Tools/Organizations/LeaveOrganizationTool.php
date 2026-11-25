<?php

namespace App\Mcp\Tools\Organizations;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Leave an organization. You lose access until someone invites you again. The last owner cannot leave; make another member an owner first.')]
class LeaveOrganizationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);
            $organization = $this->organization($request);
            $this->authorize($request, 'leave', $organization);

            $organization->removeMember($user);

            if ($user->isCurrentOrganization($organization)) {
                $user->switchToFallbackOrganization($organization);
            }

            return Response::text(__('You left the organization ":name"', ['name' => $organization->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $schema->string()->description('Slug of the organization to leave.')->required(),
        ];
    }
}
