<?php

use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MentionedInComment;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

test('a mention notifies through the database and broadcast channels with a link to the request', function () {
    Notification::fake();

    $project = Project::factory()->public()->create();
    $requester = User::factory()->create(['name' => 'Ada Lovelace']);
    $feedback = Feedback::factory()->for($project)->for($requester, 'author')->create(['title' => 'Dark mode']);
    $commenter = User::factory()->create(['name' => 'Grace Hopper']);

    $this->actingAs($commenter)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), [
            'body' => '@Ada Lovelace what do you think?',
            'mentions' => [$requester->id],
        ]);

    Notification::assertSentTo($requester, MentionedInComment::class, function (MentionedInComment $notification, array $channels) use ($requester, $project, $feedback) {
        $data = $notification->toArray($requester);

        return $channels === ['database', 'broadcast']
            && $data['title'] === 'Grace Hopper mentioned you on "Dark mode"'
            && $data['url'] === route('portal.feedback.show', [$project->organization, $project, $feedback], absolute: false);
    });
});

test('notifications are shared with the page and can be marked as read', function () {
    $user = User::factory()->withOrganization()->create();
    $other = User::factory()->create();
    $comment = Comment::factory()->create();

    $user->notify(new MentionedInComment($comment));
    $user->notify(new MentionedInComment($comment));
    $other->notify(new MentionedInComment($comment));

    $notification = $user->notifications()->latest()->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.unreadCount', 2)
            ->has('notifications.items', 2),
        );

    $this->actingAs($user)
        ->post(route('notifications.read', $notification))
        ->assertRedirect($notification->data['url']);

    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('notifications.read', $other->notifications()->first()))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('notifications can be cleared one at a time or all at once, but only your own', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $comment = Comment::factory()->create();

    $user->notify(new MentionedInComment($comment));
    $user->notify(new MentionedInComment($comment));
    $other->notify(new MentionedInComment($comment));

    $this->actingAs($user)
        ->delete(route('notifications.destroy', $user->notifications()->first()))
        ->assertRedirect();

    expect($user->notifications()->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('notifications.destroy', $other->notifications()->first()))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(route('notifications.destroy-all'))
        ->assertRedirect();

    expect($user->notifications()->count())->toBe(0);
    expect($other->notifications()->count())->toBe(1);
});
