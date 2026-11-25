<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Requests\Clients\ClientContactRequest;
use App\Http\Resources\ClientContactResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add a contact person to a client. Contacts can receive invoices and open support tickets; each email belongs to one contact in the organization.')]
class AddClientContactTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $this->authorize($request, 'update', $client);

            $validated = $this->formRequest(ClientContactRequest::class, $request, ['current_organization' => $organization, 'client' => $client], 'POST',
                collect($request->all())->only(['name', 'email'])->all());

            $contact = $client->contacts()->create($validated->validated());

            return Response::json(ClientContactResource::make($contact)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.')->required(),
            'name' => $schema->string()->description('Contact name.')->required(),
            'email' => $schema->string()->format('email')->description('Contact email address.')->required(),
        ];
    }
}
