<?php

namespace App\Mcp\Tools\Addresses;

use App\Http\Resources\AddressResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the addresses of your organization, a client or a client contact, with their ids and types (billing, mailing, office, other).')]
class ListAddressesTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $owner = $this->addressOwner($request, $this->organization($request));

            return Response::json(AddressResource::collection($owner->addresses()->get())->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            ...$this->addressOwnerArguments($schema),
        ];
    }
}
