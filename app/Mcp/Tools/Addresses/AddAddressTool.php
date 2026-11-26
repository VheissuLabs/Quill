<?php

namespace App\Mcp\Tools\Addresses;

use App\Enums\AddressType;
use App\Http\Requests\Addresses\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add an address to your organization, a client or a client contact. The Billing address of your organization prints as From on invoices, and a client\'s as Bill to.')]
class AddAddressTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $owner = $this->addressOwner($request, $organization);

            $validated = $this->formRequest(AddressRequest::class, $request, ['current_organization' => $organization], 'POST',
                collect($request->all())->only(['type', 'line1', 'line2', 'city', 'region', 'postal_code', 'country'])->all());

            return Response::json(AddressResource::make($owner->addresses()->create($validated->validated()))->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            ...$this->addressOwnerArguments($schema),
            'type' => $schema->string()->enum(array_column(AddressType::cases(), 'value'))->required(),
            'line1' => $schema->string()->description('Street address.')->required(),
            'line2' => $schema->string()->description('Suite, unit or floor.'),
            'city' => $schema->string()->required(),
            'region' => $schema->string()->description('State or region.'),
            'postal_code' => $schema->string(),
            'country' => $schema->string(),
        ];
    }
}
