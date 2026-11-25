<?php

namespace App\Mcp\Tools\Account;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Email a fresh verification link to one of your unverified additional email addresses.')]
class ResendEmailVerificationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $emailAddress = $this->user($request)->emailAddresses()->findOrFail((int) $request->get('email_address_id'));
            $this->authorize($request, 'manage', $emailAddress);

            if ($emailAddress->isVerified()) {
                return Response::text(__(':email is already verified.', ['email' => $emailAddress->email]));
            }

            if (! RateLimiter::attempt("mcp-email-verification:{$emailAddress->id}", 6, fn () => $emailAddress->sendVerificationNotification())) {
                return Response::error(__('Too many verification emails. Try again in a minute.'));
            }

            return Response::text(__('Verification link sent to :email.', ['email' => $emailAddress->email]));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'email_address_id' => $schema->integer()->description('Id of the email address; show-profile lists them.')->required(),
        ];
    }
}
