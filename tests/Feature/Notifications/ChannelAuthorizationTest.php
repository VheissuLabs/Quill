<?php

use App\Models\Organization;
use App\Models\User;

function authorizeChannel(User $user, string $channel): Illuminate\Testing\TestResponse
{
    return test()
        ->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
}

test('a user may subscribe to their own notification channel', function () {
    $user = User::factory()->create();

    authorizeChannel($user, 'private-App.Models.User.'.$user->id)->assertOk();
});

test('a user may not subscribe to another users notification channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    authorizeChannel($user, 'private-App.Models.User.'.$other->id)->assertForbidden();
})->note('Guards the (int) cast bypass: every UUID casts to 0, so a loose comparison authorizes everyone.');

test('a guest may not subscribe to a notification channel', function () {
    $user = User::factory()->create();

    $this
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])
        ->assertUnauthorized();
});

test('a member may join their organizations presence channel', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, 'member');

    authorizeChannel($user, 'presence-organizations.'.$organization->id)->assertOk();
});

test('a client contact may join their own organizations presence channel', function () {
    $contact = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->members()->attach($contact);

    $contact->assignOrganizationRole($organization, 'client');

    authorizeChannel($contact, 'presence-organizations.'.$organization->id)->assertOk();
});

test('a non member may not join an organizations presence channel', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    authorizeChannel($user, 'presence-organizations.'.$organization->id)->assertForbidden();
});

test('a user may join their own presence channel', function () {
    $user = User::factory()->create();

    authorizeChannel($user, 'presence-users.'.$user->id)->assertOk();
})->note('This is the channel the presence lookup reads occupancy from.');

test('a user may not join another users presence channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    authorizeChannel($user, 'presence-users.'.$other->id)->assertForbidden();
});

test('the presence payload carries the members name for display', function () {
    $user = User::factory()->create(['name' => 'Karl Murray']);
    $organization = Organization::factory()->create();

    $organization->members()->attach($user);

    $user->assignOrganizationRole($organization, 'owner');

    $response = authorizeChannel($user, 'presence-organizations.'.$organization->id);

    $response->assertOk();

    expect($response->json('channel_data'))->toContain('Karl Murray');
});
