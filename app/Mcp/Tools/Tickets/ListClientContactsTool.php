<?php

namespace App\Mcp\Tools\Tickets;

use App\Http\Requests\Clients\ClientContactLookupRequest;
use App\Http\Resources\ClientContactResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Ticket;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Find client contacts by name or email, to pick the requester when opening a ticket with create-ticket. Returns up to 8 matches with their client.')]
class ListClientContactsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Ticket::class, $organization]);

            $lookup = $this->formRequest(ClientContactLookupRequest::class, $request, ['current_organization' => $organization], 'GET', [
                'q' => $request->get('search'),
            ]);

            $contacts = mb_strlen($lookup->term()) < 2
                ? collect()
                : $organization->clientContacts()->matching($lookup->term())->get();

            return Response::json(ClientContactResource::collection($contacts)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'search' => $schema->string()->description('At least two characters of a contact\'s name or email.')->required(),
        ];
    }
}
