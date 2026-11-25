<?php

namespace App\Mcp\Tools\Notifications;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete all of your notifications, read and unread.')]
class ClearNotificationsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $this->user($request)->notifications()->delete();

            return Response::text(__('Notifications cleared.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
