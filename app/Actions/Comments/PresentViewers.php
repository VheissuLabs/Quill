<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\Feature;
use App\Models\Issue;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

/**
 * Who is watching the page right now, according to the presence channel.
 *
 * Any doubt answers with nobody, so the notification is sent rather than swallowed: a duplicate
 * is an annoyance, a missing one means somebody never learns they were answered.
 */
class PresentViewers
{
    /** @return array<int, int> */
    public function handle(Comment $comment): array
    {
        $channel = $this->channelFor($comment);
        $broadcaster = Broadcast::connection();

        if ($channel === null || ! $broadcaster instanceof PusherBroadcaster) {
            return [];
        }

        try {
            $response = $broadcaster->getPusher()->get("/channels/{$channel}/users");
        } catch (Throwable) {
            return [];
        }

        return $this->ids($response);
    }

    /** @return array<int, int> */
    private function ids(mixed $response): array
    {
        $decoded = json_decode(json_encode($response) ?: '[]', true);
        $users = is_array($decoded) && isset($decoded['users']) && is_array($decoded['users'])
            ? $decoded['users']
            : [];

        $ids = [];

        foreach ($users as $user) {
            if (is_array($user) && isset($user['id'])) {
                $ids[] = (int) $user['id'];
            }
        }

        return $ids;
    }

    private function channelFor(Comment $comment): ?string
    {
        return match ($comment->commentable_type) {
            (new Feature)->getMorphClass() => 'presence-features.'.$comment->commentable_id,
            (new Issue)->getMorphClass() => 'presence-issues.'.$comment->commentable_id,
            default => null,
        };
    }
}
