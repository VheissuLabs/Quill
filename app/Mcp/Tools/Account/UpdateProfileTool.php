<?php

namespace App\Mcp\Tools\Account;

use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change your name or primary email address. Omitted fields keep their current value. A new primary email has to be verified again.')]
class UpdateProfileTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);

            $validated = $this->formRequest(ProfileUpdateRequest::class, $request, [], 'PATCH', [
                'name' => $request->get('name', $user->name),
                'email' => $request->get('email', $user->email),
            ]);

            $user->updateProfile($validated->validated());

            return Response::json([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Your display name.'),
            'email' => $schema->string()->description('Your primary email address.'),
        ];
    }
}
