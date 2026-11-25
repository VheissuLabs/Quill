<?php

namespace App\Mcp\Tools\Account;

use App\Http\Requests\Settings\StoreEmailAddressRequest;
use App\Http\Resources\EmailAddressResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Add another email address to your account, so invitations and support mail sent to it reach you. A verification link is emailed to it; it only counts once verified.')]
class AddEmailAddressTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $validated = $this->formRequest(StoreEmailAddressRequest::class, $request, [], input: ['email' => $request->get('email')]);

            $emailAddress = $this->user($request)->emailAddresses()->create($validated->validated());

            $emailAddress->sendVerificationNotification();

            return Response::json(EmailAddressResource::make($emailAddress)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'email' => $schema->string()->description('The email address to add.')->required(),
        ];
    }
}
