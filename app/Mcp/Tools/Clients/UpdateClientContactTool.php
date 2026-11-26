<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Requests\Clients\ClientContactRequest;
use App\Http\Resources\ClientContactResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change a client contact\'s name, job title or email. Send only the fields to change. show-client lists contact ids.')]
class UpdateClientContactTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $contact = $client->contacts()->findOrFail((int) $request->get('contact_id'));
            $this->authorize($request, 'update', $client);

            $validated = $this->formRequest(ClientContactRequest::class, $request, ['current_organization' => $organization, 'client' => $client, 'contact' => $contact], 'PATCH', [
                'name' => $contact->name,
                'title' => $contact->title,
                'email' => $contact->email,
                'phone' => $contact->phone,
                ...collect($request->all())->only(['name', 'title', 'email', 'phone'])->all(),
            ]);

            $contact->update($validated->validated());

            return Response::json(ClientContactResource::make($contact)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.')->required(),
            'contact_id' => $schema->integer()->description('Contact id.')->required(),
            'name' => $schema->string(),
            'phone' => $schema->string()->description('Phone number.'),
            'title' => $schema->string()->description('Job title, e.g. CTO. Empty clears it.'),
            'email' => $schema->string()->format('email'),
        ];
    }
}
