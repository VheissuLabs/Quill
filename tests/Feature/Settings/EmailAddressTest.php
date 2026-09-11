<?php

use App\Enums\OrganizationRole;
use App\Models\EmailAddress;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('email addresses can be added and receive a verification link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('email-addresses.store'), [
            'email' => 'Karl@Example-Corp.com',
        ]);

    $response->assertRedirect(route('profile.edit'));

    $this->assertDatabaseHas('email_addresses', [
        'user_id' => $user->id,
        'email' => 'karl@example-corp.com',
        'verified_at' => null,
    ]);

    Notification::assertSentOnDemand(
        VerifyEmailAddress::class,
        fn (VerifyEmailAddress $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'karl@example-corp.com',
    );
});

test('an address already used by another account cannot be added', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'taken@example.com']);
    EmailAddress::factory()->for($other)->create(['email' => 'also-taken@example.com']);

    $this->actingAs($user)
        ->post(route('email-addresses.store'), ['email' => 'taken@example.com'])
        ->assertSessionHasErrors(['email' => 'This email address is already in use.']);

    $this->actingAs($user)
        ->post(route('email-addresses.store'), ['email' => 'also-taken@example.com'])
        ->assertSessionHasErrors('email');
});

test('the signed verification link marks the address as verified', function () {
    $user = User::factory()->create();
    $emailAddress = EmailAddress::factory()->for($user)->create();

    $verificationUrl = URL::temporarySignedRoute('email-addresses.verify', now()->addMinutes(60), [
        'emailAddress' => $emailAddress->id,
        'hash' => sha1($emailAddress->email),
    ]);

    $response = $this->actingAs($user)->get($verificationUrl);

    $response->assertRedirect(route('profile.edit'));

    expect($emailAddress->fresh()->isVerified())->toBeTrue();
});

test('a verification link cannot be used by another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $emailAddress = EmailAddress::factory()->for($owner)->create();

    $verificationUrl = URL::temporarySignedRoute('email-addresses.verify', now()->addMinutes(60), [
        'emailAddress' => $emailAddress->id,
        'hash' => sha1($emailAddress->email),
    ]);

    $this->actingAs($intruder)->get($verificationUrl)->assertForbidden();

    expect($emailAddress->fresh()->isVerified())->toBeFalse();
});

test('email addresses can be removed by their owner only', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $emailAddress = EmailAddress::factory()->for($owner)->create();

    $this->actingAs($intruder)->delete(route('email-addresses.destroy', $emailAddress))->assertForbidden();
    $this->actingAs($owner)->delete(route('email-addresses.destroy', $emailAddress))->assertRedirect(route('profile.edit'));

    $this->assertModelMissing($emailAddress);
});

test('the profile page lists the account email addresses', function () {
    $user = User::factory()->create();
    EmailAddress::factory()->for($user)->verified()->create(['email' => 'second@example.com']);

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('emailAddresses', 1)
        ->where('emailAddresses.0.email', 'second@example.com')
        ->where('emailAddresses.0.verified', true),
    );
});

test('users can log in with a verified additional address', function () {
    $user = User::factory()->withOrganization()->create();
    EmailAddress::factory()->for($user)->verified()->create(['email' => 'karl@example-corp.com']);

    $response = $this->post(route('login.store'), [
        'email' => 'karl@example-corp.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});

test('users cannot log in with an unverified additional address', function () {
    $user = User::factory()->create();
    EmailAddress::factory()->for($user)->create(['email' => 'karl@example-corp.com']);

    $response = $this->post(route('login.store'), [
        'email' => 'karl@example-corp.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('invitations sent to a verified additional address can be accepted', function () {
    $owner = User::factory()->create();
    $user = User::factory()->withOrganization()->create();
    EmailAddress::factory()->for($user)->verified()->create(['email' => 'karl@example-corp.com']);

    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $organization->addMember($owner, OrganizationRole::Owner);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'karl@example-corp.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.organization.name', 'NotaryDash'),
        );

    $this->actingAs($user)
        ->post(route('invitations.accept', $invitation))
        ->assertRedirect();

    expect($user->fresh()->belongsToOrganization($organization))->toBeTrue();
});

test('invitations sent to an unverified additional address cannot be accepted', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    EmailAddress::factory()->for($user)->create(['email' => 'karl@example-corp.com']);

    $organization = Organization::factory()->create();
    $organization->addMember($owner, OrganizationRole::Owner);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'karl@example-corp.com',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->post(route('invitations.accept', $invitation))
        ->assertSessionHasErrors('invitation');

    expect($user->fresh()->belongsToOrganization($organization))->toBeFalse();
});
