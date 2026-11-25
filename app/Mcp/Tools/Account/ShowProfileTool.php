<?php

namespace App\Mcp\Tools\Account;

use App\Http\Resources\EmailAddressResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show your own profile: your user id, name, primary email and whether it is verified, and your additional email addresses.')]
class ShowProfileTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);

            return Response::json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'emailAddresses' => EmailAddressResource::collection($user->emailAddresses()->oldest()->get())->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
