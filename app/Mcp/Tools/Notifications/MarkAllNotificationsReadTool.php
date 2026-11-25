<?php

namespace App\Mcp\Tools\Notifications;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Mark all of your notifications as read.')]
class MarkAllNotificationsReadTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $this->user($request)->unreadNotifications->markAsRead();

            return Response::text(__('All notifications marked as read.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
