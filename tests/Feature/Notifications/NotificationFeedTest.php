<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

function storeNotification(User $user, string $title, string $organizationName, ?string $readAt = null): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\Teams\\TeamInvitation',
        'data' => [
            'title' => $title,
            'organization_name' => $organizationName,
        ],
        'read_at' => $readAt,
    ]);
}

test('a user with no notifications has an empty feed', function () {
    $user = User::factory()->create();

    expect($user->toUserNotifications())->toBeEmpty();
    expect($user->unreadNotificationCount())->toBe(0);
});

test('the feed carries what the sidebar needs to render a row', function () {
    $user = User::factory()->create();

    storeNotification($user, 'Jen invited you to Development', 'NotaryDash');

    $notification = $user->toUserNotifications()->first();

    expect($notification->title)->toBe('Jen invited you to Development');
    expect($notification->organizationName)->toBe('NotaryDash');
    expect($notification->isRead)->toBeFalse();
    expect($notification->createdAtDiff)->toBeString()->not->toBeEmpty();
    expect(Str::isUuid($notification->id))->toBeTrue();
});

test('the unread count ignores notifications already read', function () {
    $user = User::factory()->create();

    storeNotification($user, 'Unread one', 'NotaryDash');
    storeNotification($user, 'Unread two', 'NotaryDash');
    storeNotification($user, 'Already seen', 'NotaryDash', readAt: now()->toDateTimeString());

    expect($user->unreadNotificationCount())->toBe(2);
    expect($user->toUserNotifications())->toHaveCount(3);
});

test('the feed is newest first', function () {
    $user = User::factory()->create();

    storeNotification($user, 'Older', 'NotaryDash');
    $this->travel(1)->minutes();
    storeNotification($user, 'Newer', 'NotaryDash');

    expect($user->toUserNotifications()->pluck('title')->all())->toBe(['Newer', 'Older']);
});

test('the feed is capped at fifteen', function () {
    $user = User::factory()->create();

    foreach (range(1, 20) as $i) {
        storeNotification($user, "Notification {$i}", 'NotaryDash');
    }

    expect($user->toUserNotifications())->toHaveCount(15);
    expect($user->unreadNotificationCount())->toBe(20, 'the count is not capped, only the list');
});

test('a notification missing its organization name still renders', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\Teams\\TeamInvitation',
        'data' => ['title' => 'Something happened'],
    ]);

    $notification = $user->toUserNotifications()->first();

    expect($notification->title)->toBe('Something happened');
    expect($notification->organizationName)->toBeNull();
})->note('Producers must supply organization_name, but a malformed row must not break the sidebar.');

test('a notification with no title falls back rather than rendering blank', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\Teams\\TeamInvitation',
        'data' => ['organization_name' => 'NotaryDash'],
    ]);

    expect($user->toUserNotifications()->first()->title)->toBe('Notification');
});

test('the sidebar receives the feed and the unread count on every page', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);

    $organization->members()->attach($user, ['role' => 'owner']);
    $user->switchOrganization($organization);

    storeNotification($user, 'Jen invited you to Development', 'NotaryDash');
    $this->travel(1)->minutes();
    storeNotification($user, 'Already seen', 'NotaryDash', readAt: now()->toDateTimeString());

    $this
        ->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications', 2)
            ->where('notifications.0.title', 'Already seen')
            ->where('unreadNotificationCount', 1),
        );
});

test('a guest page carries an empty feed', function () {
    $this
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('notifications', [])
            ->where('unreadNotificationCount', 0),
        );
});
