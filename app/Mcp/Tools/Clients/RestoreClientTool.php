<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Resources\ClientResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Restore a deleted client. list-clients with deleted=true shows them. Projects and email domains removed on delete are not relinked.')]
class RestoreClientTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->onlyTrashed()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $this->authorize($request, 'restore', $client);

            $client->restore();

            return Response::json(ClientResource::make($client)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Deleted client\'s slug or id.')->required(),
        ];
    }
}
