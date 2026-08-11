<?php

namespace App\Concerns;

use App\Data\UserNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

trait HasNotificationFeed
{
    /**
     * How many notifications the sidebar popover carries. The popover scrolls but
     * does not paginate, and there is no history page to fall back to.
     */
    public const int FEED_LIMIT = 15;

    /**
     * Newest first, with `id` as a tiebreak so notifications created within the
     * same second keep a stable order between requests rather than shuffling.
     *
     * @return Collection<int, UserNotification>
     */
    public function toUserNotifications(): Collection
    {
        return $this->notifications()
            ->latest()
            ->orderByDesc('id')
            ->limit(self::FEED_LIMIT)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->toUserNotification($notification));
    }

    public function toUserNotification(DatabaseNotification $notification): UserNotification
    {
        /**
         * Producers are expected to supply `title` and `organization_name`, but a
         * malformed row must not take the sidebar down with it — a notification
         * nobody can read is better than a page nobody can load.
         */
        $data = $notification->data;

        return new UserNotification(
            id: $notification->id,
            title: $data['title'] ?? __('Notification'),
            organizationName: $data['organization_name'] ?? null,
            createdAtDiff: $notification->created_at->diffForHumans(short: true),
            isRead: $notification->read_at !== null,
        );
    }

    public function unreadNotificationCount(): int
    {
        return $this->unreadNotifications()->count();
    }
}
