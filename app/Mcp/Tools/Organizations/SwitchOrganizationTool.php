<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\UserOrganizationResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Make an organization your current one. Tools called without an organization argument then act on it, and the web app opens on it.')]
class SwitchOrganizationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);
            $organization = $this->organization($request);

            $user->switchOrganization($organization);

            return Response::json(UserOrganizationResource::make($organization, $user)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $schema->string()->description('Slug of the organization to switch to; list-organizations shows the slugs.')->required(),
        ];
    }
}
