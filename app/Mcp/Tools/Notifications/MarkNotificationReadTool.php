<?php

namespace App\Mcp\Tools\Notifications;

use App\Http\Resources\NotificationResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Mark one of your notifications as read.')]
class MarkNotificationReadTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $notification = $this->user($request)->notifications()->findOrFail((string) $request->get('notification_id'));

            $notification->markAsRead();

            return Response::json(NotificationResource::make($notification)->resolve());
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'notification_id' => $schema->string()->description('Notification id; list-notifications shows them.')->required(),
        ];
    }
}
