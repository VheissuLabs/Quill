<?php

namespace App\Mcp\Tools\Organizations;

use App\Enums\OrganizationPermission;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the permissions a role can grant, with their value, label and group. create-role and update-role take the values.')]
class ListPermissionsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $this->user($request);

            return Response::json(OrganizationPermission::options());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
