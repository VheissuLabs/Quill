<?php

namespace App\Mcp\Tools\Account;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Remove one of your additional email addresses. Your primary email cannot be removed, only changed with update-profile.')]
class RemoveEmailAddressTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $emailAddress = $this->user($request)->emailAddresses()->findOrFail((int) $request->get('email_address_id'));
            $this->authorize($request, 'manage', $emailAddress);

            $emailAddress->delete();

            return Response::text(__('Email address :email removed.', ['email' => $emailAddress->email]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'email_address_id' => $schema->integer()->description('Id of the email address; show-profile lists them.')->required(),
        ];
    }
}
