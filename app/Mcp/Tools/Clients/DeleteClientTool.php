<?php

namespace App\Mcp\Tools\Clients;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete a client. Its invoices and contacts are kept, its projects are unlinked, its email domains are removed and its billing cycle stops. restore-client brings it back.')]
class DeleteClientTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $this->authorize($request, 'delete', $client);

            $client->deleteKeepingRecords();

            return Response::text(__('Deleted :client.', ['client' => $client->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.')->required(),
        ];
    }
}
