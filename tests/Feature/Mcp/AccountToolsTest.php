<?php

use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Account\AddEmailAddressTool;
use App\Mcp\Tools\Account\RemoveEmailAddressTool;
use App\Mcp\Tools\Account\ResendEmailVerificationTool;
use App\Mcp\Tools\Account\ShowProfileTool;
use App\Mcp\Tools\Account\UpdateProfileTool;
use App\Mcp\Tools\Notifications\ClearNotificationsTool;
use App\Mcp\Tools\Notifications\DeleteNotificationTool;
use App\Mcp\Tools\Notifications\ListNotificationsTool;
use App\Mcp\Tools\Notifications\MarkAllNotificationsReadTool;
use App\Mcp\Tools\Notifications\MarkNotificationReadTool;
use App\Models\Comment;
use App\Models\EmailAddress;
use App\Models\User;
use App\Notifications\MentionedInComment;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Support\Facades\Notification;

function accountToolNotifiedUser(int $count = 2): User
{
    $user = User::factory()->withOrganization()->create();
    $comment = Comment::factory()->create();

    foreach (range(1, $count) as $ignored) {
        $user->notify(new MentionedInComment($comment));
    }

    return $user;
}

test('it shows the profile with additional email addresses', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    EmailAddress::factory()->for($user)->verified()->create(['email' => 'ada@work.test']);

    QuillServer::actingAs($user)->tool(ShowProfileTool::class)
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('ada@work.test');
});

test('it updates the name and resets verification when the email changes', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    QuillServer::actingAs($user)->tool(UpdateProfileTool::class, ['name' => 'New Name'])->assertOk();

    expect($user->fresh())->name->toBe('New Name')->email_verified_at->not->toBeNull();

    QuillServer::actingAs($user)->tool(UpdateProfileTool::class, ['email' => 'fresh@example.com'])->assertOk();

    expect($user->fresh())->email->toBe('fresh@example.com')->email_verified_at->toBeNull();
});

test('updating the profile uses the web validation', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    QuillServer::actingAs($user)->tool(UpdateProfileTool::class, ['email' => $other->email])->assertHasErrors(['email']);
});

test('it adds an email address and sends a verification link', function () {
    Notification::fake();
    $user = User::factory()->create();

    QuillServer::actingAs($user)->tool(AddEmailAddressTool::class, ['email' => ' Second@Example.com '])->assertOk();

    $emailAddress = $user->emailAddresses()->sole();

    expect($emailAddress)->email->toBe('second@example.com')->verified_at->toBeNull();
    Notification::assertSentOnDemand(VerifyEmailAddress::class);
});

test('an email address already in use cannot be added', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    QuillServer::actingAs($user)->tool(AddEmailAddressTool::class, ['email' => $other->email])->assertHasErrors(['already in use']);

    expect($user->emailAddresses()->exists())->toBeFalse();
});

test('it resends verification for an unverified address', function () {
    Notification::fake();
    $user = User::factory()->create();
    $emailAddress = EmailAddress::factory()->for($user)->create();

    QuillServer::actingAs($user)->tool(ResendEmailVerificationTool::class, ['email_address_id' => $emailAddress->id])->assertOk();

    Notification::assertSentOnDemand(VerifyEmailAddress::class);
});

test('it removes only the user own email addresses', function () {
    $user = User::factory()->create();
    $own = EmailAddress::factory()->for($user)->create();
    $foreign = EmailAddress::factory()->create();

    QuillServer::actingAs($user)->tool(RemoveEmailAddressTool::class, ['email_address_id' => $foreign->id])->assertHasErrors();
    QuillServer::actingAs($user)->tool(ResendEmailVerificationTool::class, ['email_address_id' => $foreign->id])->assertHasErrors();
    QuillServer::actingAs($user)->tool(RemoveEmailAddressTool::class, ['email_address_id' => $own->id])->assertOk();

    expect($own->fresh())->toBeNull()
        ->and($foreign->fresh())->not->toBeNull();
});

test('it lists notifications and marks one or all read', function () {
    $user = accountToolNotifiedUser(3);
    [$first] = $user->notifications()->latest()->get();

    QuillServer::actingAs($user)->tool(ListNotificationsTool::class)
        ->assertOk()
        ->assertSee('"unreadCount":3')
        ->assertSee($first->id);

    QuillServer::actingAs($user)->tool(MarkNotificationReadTool::class, ['notification_id' => $first->id])->assertOk();

    expect($user->unreadNotifications()->count())->toBe(2);

    QuillServer::actingAs($user)->tool(ListNotificationsTool::class, ['unread_only' => true])->assertOk()->assertDontSee($first->id);
    QuillServer::actingAs($user)->tool(MarkAllNotificationsReadTool::class)->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('it deletes one notification and clears the rest', function () {
    $user = accountToolNotifiedUser(3);
    $notification = $user->notifications()->first();

    QuillServer::actingAs($user)->tool(DeleteNotificationTool::class, ['notification_id' => $notification->id])->assertOk();

    expect($user->notifications()->count())->toBe(2);

    QuillServer::actingAs($user)->tool(ClearNotificationsTool::class)->assertOk();

    expect($user->notifications()->count())->toBe(0);
});

test('notifications of another user cannot be read or deleted', function () {
    $user = accountToolNotifiedUser(1);
    $other = accountToolNotifiedUser(1);
    $foreign = $other->notifications()->sole();

    QuillServer::actingAs($user)->tool(MarkNotificationReadTool::class, ['notification_id' => $foreign->id])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeleteNotificationTool::class, ['notification_id' => $foreign->id])->assertHasErrors();
    QuillServer::actingAs($user)->tool(ClearNotificationsTool::class)->assertOk();

    expect($foreign->fresh())->not->toBeNull()->read_at->toBeNull();
});
