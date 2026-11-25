<?php

namespace App\Mcp\Tools\Notifications;

use App\Http\Resources\NotificationResource;
use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List your notifications, newest first, with the unread count. Each has the id the other notification tools take.')]
class ListNotificationsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $user = $this->user($request);

            $notifications = $request->boolean('unread_only') ? $user->unreadNotifications() : $user->notifications();

            return Response::json([
                'unreadCount' => $user->unreadNotifications()->count(),
                'items' => NotificationResource::collection($notifications->latest()->limit(min(max($request->integer('limit', 20), 1), 100))->get())->resolve(),
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'unread_only' => $schema->boolean()->description('Only list unread notifications.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('How many to return, 20 by default.'),
        ];
    }
}
