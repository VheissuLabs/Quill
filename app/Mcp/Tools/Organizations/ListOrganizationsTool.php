<?php

namespace App\Mcp\Tools\Organizations;

use App\Http\Resources\UserOrganizationResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the organizations you belong to. Every other tool takes one of these slugs as its organization argument and defaults to the current one.')]
class ListOrganizationsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        $user = $this->user($request);

        return Response::json($user->memberOrganizations(includeCurrent: true)->map(fn ($organization) => UserOrganizationResource::make($organization, $user)->resolve())->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
