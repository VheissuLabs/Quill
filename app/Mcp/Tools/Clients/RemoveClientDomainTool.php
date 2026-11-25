<?php

namespace App\Mcp\Tools\Clients;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove an email domain from a client. show-client lists domain ids.')]
class RemoveClientDomainTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $domain = $client->domains()->findOrFail((int) $request->get('domain_id'));
            $this->authorize($request, 'update', $client);

            $domain->delete();

            return Response::text(__('Removed :domain from :client.', ['domain' => $domain->domain, 'client' => $client->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.')->required(),
            'domain_id' => $schema->integer()->description('Domain id.')->required(),
        ];
    }
}
