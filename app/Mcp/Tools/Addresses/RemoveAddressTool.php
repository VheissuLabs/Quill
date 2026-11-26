<?php

namespace App\Mcp\Tools\Addresses;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove an address from your organization, a client or a client contact.')]
class RemoveAddressTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $address = $this->addressOwner($request, $this->organization($request))->addresses()->findOrFail((int) $request->get('address_id'));

            $address->delete();

            return Response::text(__('Removed the :type address.', ['type' => strtolower($address->type->label())]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            ...$this->addressOwnerArguments($schema),
            'address_id' => $schema->integer()->description('Address id.')->required(),
        ];
    }
}
