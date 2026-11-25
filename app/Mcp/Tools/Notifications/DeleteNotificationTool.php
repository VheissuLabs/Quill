<?php

namespace App\Mcp\Tools\Notifications;

use App\Mcp\Tools\QuillTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete one of your notifications.')]
class DeleteNotificationTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $this->user($request)->notifications()->findOrFail((string) $request->get('notification_id'))->delete();

            return Response::text(__('Notification deleted.'));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'notification_id' => $schema->string()->description('Notification id; list-notifications shows them.')->required(),
        ];
    }
}
