<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /** @return array{id: string, title: string, body: string, url: string|null, readAt: string|null, createdAt: string} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => (string) ($this->data['title'] ?? ''),
            'body' => (string) ($this->data['body'] ?? ''),
            'url' => $this->data['url'] ?? null,
            'readAt' => $this->read_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString() ?? now()->toISOString(),
        ];
    }
}
