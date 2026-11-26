<?php

namespace App\Mcp\Tools\Clients;

use App\Http\Resources\ClientBillingResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\InvoiceListResource;
use App\Http\Resources\ProjectResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show a client with its contacts, addresses, email domains and projects. For people who manage invoices it also includes billing (rate, billing contact, billing cycle, payment terms) and the 10 most recent invoices.')]
class ShowClientTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $user = $this->user($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $this->authorize($request, 'view', $client);

            $managesBilling = $organization->allowsInvoiceManagementBy($user);

            return Response::json([
                'client' => ClientResource::make($client->load([
                    'contacts' => fn ($query) => $query->orderBy('name'),
                    'domains' => fn ($query) => $query->orderBy('domain'),
                    'addresses',
                ]))->resolve(),
                'projects' => ProjectResource::collection($client->projects()->visibleTo($user)->orderBy('name')->get())->resolve(),
                'billing' => $managesBilling ? ClientBillingResource::make($client)->resolve() : null,
                'recentInvoices' => $managesBilling
                    ? InvoiceListResource::collection($client->invoices()->with('client:id,name,slug')->orderByDesc('issue_date')->orderByDesc('id')->limit(10)->get())->resolve()
                    : null,
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id; list-clients shows both.')->required(),
        ];
    }
}
