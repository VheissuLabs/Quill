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

#[Description('Change an address. Send only the fields to change; list-addresses shows address ids.')]
class UpdateAddressTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $address = $this->addressOwner($request, $organization)->addresses()->findOrFail((int) $request->get('address_id'));

            $validated = $this->formRequest(AddressRequest::class, $request, ['current_organization' => $organization], 'PATCH', [
                ...$address->only(['line1', 'line2', 'city', 'region', 'postal_code', 'country']),
                'type' => $address->type->value,
                ...collect($request->all())->only(['type', 'line1', 'line2', 'city', 'region', 'postal_code', 'country'])->all(),
            ]);

            $address->update($validated->validated());

            return Response::json(AddressResource::make($address)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            ...$this->addressOwnerArguments($schema),
            'address_id' => $schema->integer()->description('Address id.')->required(),
            'type' => $schema->string()->enum(array_column(AddressType::cases(), 'value')),
            'line1' => $schema->string(),
            'line2' => $schema->string(),
            'city' => $schema->string(),
            'region' => $schema->string(),
            'postal_code' => $schema->string(),
            'country' => $schema->string(),
        ];
    }
}
