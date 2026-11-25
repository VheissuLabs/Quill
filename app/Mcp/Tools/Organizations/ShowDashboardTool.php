<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\DashboardResource;
use App\Http\Resources\PendingInvitationResource;
use App\Mcp\Tools\QuillTool;
use App\Models\OrganizationInvitation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show an organization\'s dashboard: your pending invitations, the projects you can see with their feedback counts, the latest feedback, and totals for projects, open feedback and votes.')]
class ShowDashboardTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);
            $organization = $this->organization($request);

            return Response::json([
                'pendingInvitations' => PendingInvitationResource::collection(OrganizationInvitation::pendingFor($user)->get())->resolve(),
                ...DashboardResource::make($organization, $user)->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
