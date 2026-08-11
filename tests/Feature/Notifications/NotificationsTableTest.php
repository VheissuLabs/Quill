<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('the notifiable id is a uuid column, not an integer', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue();
    expect(Schema::getColumnType('notifications', 'notifiable_id'))->not->toBe('integer');
    expect(Schema::getColumnType('notifications', 'notifiable_id'))->not->toBe('bigint');
});

test('a notification row can be stored against a user and read back', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\Teams\\TeamInvitation',
        'data' => ['organization_name' => 'NotaryDash'],
    ]);

    $notification = $user->fresh()->notifications->first();

    expect($notification)->not->toBeNull();
    expect($notification->data['organization_name'])->toBe('NotaryDash');
    expect($notification->notifiable_id)->toBe($user->id);
    expect($notification->read_at)->toBeNull();
});

test('a notification can be marked read', function () {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid7(),
        'type' => 'App\\Notifications\\Teams\\TeamInvitation',
        'data' => [],
    ]);

    $user->unreadNotifications->first()->markAsRead();

    expect($user->fresh()->unreadNotifications)->toBeEmpty();
    expect($user->fresh()->notifications)->toHaveCount(1);
});
