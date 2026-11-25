<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Requests\Clients\StoreClientDomainRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add an email domain to a client, such as acme.com, so support mail from that domain is filed under the client. Free mail providers are refused, and a domain can belong to one client.')]
class AddClientDomainTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $this->authorize($request, 'update', $client);

            $validated = $this->formRequest(StoreClientDomainRequest::class, $request, ['current_organization' => $organization, 'client' => $client], 'POST',
                collect($request->all())->only(['domain'])->all());

            $domain = $client->domains()->create(['domain' => $validated->validated('domain')]);

            return Response::json(['id' => $domain->id, 'domain' => $domain->domain]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.')->required(),
            'domain' => $schema->string()->description('Email domain, e.g. acme.com.')->required(),
        ];
    }
}
