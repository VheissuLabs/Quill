<?php

namespace App\Mcp\Tools\Clients;

use App\Enums\BillingCycle;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Mcp\Tools\QuillTool;
use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a client. Billing fields are only accepted from people who manage invoices; add contacts afterwards with add-client-contact and pick the billing contact with update-client.')]
class CreateClientTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'create', [Client::class, $organization]);

            $validated = $this->formRequest(ClientRequest::class, $request, ['current_organization' => $organization]);

            $client = $organization->clients()->make();
            $client->saveWithBillingCycle($validated->validated());

            return Response::json(ClientResource::make($client->refresh())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'name' => $schema->string()->description('Client name.')->required(),
            'billing_rate' => $schema->number()->description('Hourly rate for this client\'s work; overrides the organization default.'),
            'billing_cycle' => $schema->string()->enum(array_column(BillingCycle::cases(), 'value'))->description('Automatic invoicing cycle, when billing cycles are enabled. Omit for manual invoicing.'),
            'billing_cycle_starts_on' => $schema->string()->format('date')->description('YYYY-MM-DD the weekly or biweekly cycle counts from. Defaults to today.'),
            'billing_cc_emails' => $schema->array()->items($schema->string()->format('email'))->description('Extra addresses copied on invoices.'),
            'payment_terms_days' => $schema->integer()->description('Days until an invoice is due; overrides the organization default.'),
            'billed_through_client_id' => $schema->integer()->description('Id of another client whose invoices carry this client\'s work.'),
        ];
    }
}
