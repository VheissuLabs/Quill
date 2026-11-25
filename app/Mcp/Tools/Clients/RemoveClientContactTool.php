<?php

namespace App\Mcp\Tools\Clients;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove a contact from a client.')]
class RemoveClientContactTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $contact = $client->contacts()->findOrFail((int) $request->get('contact_id'));
            $this->authorize($request, 'update', $client);

            $contact->delete();

            return Response::text(__('Removed :email from :client.', ['email' => $contact->email, 'client' => $client->name]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.')->required(),
            'contact_id' => $schema->integer()->description('Contact id.')->required(),
        ];
    }
}
