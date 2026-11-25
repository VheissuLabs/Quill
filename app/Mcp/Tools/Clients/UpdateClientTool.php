<?php

namespace App\Mcp\Tools\Clients;

use App\Enums\BillingCycle;
use App\Http\Requests\Clients\ClientRequest;
use App\Http\Resources\ClientBillingResource;
use App\Http\Resources\ClientResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change a client\'s name or billing. Send only the fields to change; send null to clear a billing field. Setting a billing_cycle opens the current period\'s invoice; clearing it stops automatic invoicing. Billing fields need the manage-invoices permission.')]
class UpdateClientTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $client = $organization->clients()->identifiedBy((string) $request->get('client'))->firstOrFail();
            $this->authorize($request, 'update', $client);

            $validated = $this->formRequest(ClientRequest::class, $request, ['current_organization' => $organization, 'client' => $client], 'PATCH', [
                'name' => $client->name,
                ...collect($request->all())->except(['organization', 'client'])->all(),
            ]);

            $client->saveWithBillingCycle($validated->validated());

            return Response::json([
                'client' => ClientResource::make($client->refresh())->resolve(),
                'billing' => $organization->allowsInvoiceManagementBy($this->user($request)) ? ClientBillingResource::make($client)->resolve() : null,
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'client' => $schema->string()->description('Client slug or id.')->required(),
            'name' => $schema->string(),
            'billing_rate' => $schema->number()->description('Hourly rate for this client\'s work; null falls back to the organization default.'),
            'billing_contact_id' => $schema->integer()->description('Id of one of this client\'s contacts who receives invoices.'),
            'billing_cycle' => $schema->string()->enum(array_column(BillingCycle::cases(), 'value'))->description('Automatic invoicing cycle, when billing cycles are enabled; null stops it.'),
            'billing_cycle_starts_on' => $schema->string()->format('date')->description('YYYY-MM-DD the weekly or biweekly cycle counts from. Defaults to today.'),
            'billing_cc_emails' => $schema->array()->items($schema->string()->format('email'))->description('Extra addresses copied on invoices.'),
            'payment_terms_days' => $schema->integer()->description('Days until an invoice is due; null falls back to the organization default.'),
            'billed_through_client_id' => $schema->integer()->description('Id of another client whose invoices carry this client\'s work; null to bill it directly.'),
        ];
    }
}
