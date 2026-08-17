<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Team;
use App\Models\User;

function invitationFor(string $email = 'lucy@acme.test', string $clientName = 'Acme Title'): OrganizationInvitation
{
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create(['name' => $clientName]);

    return OrganizationInvitation::factory()->forClient($client)->create([
        'email' => $email,
        'invited_by' => $owner->id,
    ]);
}

test('the join page shows who invited them and to what', function () {
    $invitation = invitationFor();

    $this->get(route('join.show', ['invitation' => $invitation->code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Join')
            ->where('invitation.email', 'lucy@acme.test')
            ->where('invitation.organizationName', 'NotaryDash')
            ->where('invitation.clientName', 'Acme Title')
            ->has('passwordRules'),
        );
});

test('joining creates the account, accepts the invitation, and signs them in', function () {
    $invitation = invitationFor();

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => 'Lucy Alvarez',
        'password' => 'sixteen-char-password',
        'password_confirmation' => 'sixteen-char-password',
    ])->assertRedirect(route('home'));

    $user = User::where('email', 'lucy@acme.test')->sole();

    expect($user->name)->toBe('Lucy Alvarez');
    expect($user->organizationRoleName($invitation->organization))->toBe('client');
    expect($invitation->fresh()->isAccepted())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
});

test('the invited email is verified without a second round trip', function () {
    $invitation = invitationFor();

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => 'Lucy Alvarez',
        'password' => 'sixteen-char-password',
        'password_confirmation' => 'sixteen-char-password',
    ]);

    expect(User::where('email', 'lucy@acme.test')->sole()->hasVerifiedEmail())->toBeTrue();
})->note('Accepting from the invitation sent to that address is the proof a verification email would ask for.');

test('a joining contact gets no organization and no personal team of their own', function () {
    $invitation = invitationFor();

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => 'Lucy Alvarez',
        'password' => 'sixteen-char-password',
        'password_confirmation' => 'sixteen-char-password',
    ]);

    $user = User::where('email', 'lucy@acme.test')->sole();

    expect($user->organizations()->count())->toBe(1);
    expect($user->organizations()->sole()->id)->toBe($invitation->organization_id);
    expect(Team::where('is_personal', true)->whereRelation('members', 'users.id', $user->id)->count())->toBe(0);
})->note('They are joining someone else\'s organization; a client contact holds no team membership.');

test('the contact appears against their client once they join', function () {
    $invitation = invitationFor();
    $client = $invitation->client;

    expect($client->contacts()->count())->toBe(0);

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => 'Lucy Alvarez',
        'password' => 'sixteen-char-password',
        'password_confirmation' => 'sixteen-char-password',
    ]);

    expect($client->contacts()->with('user')->get()->pluck('user.name')->all())->toBe(['Lucy Alvarez']);
});

test('someone who already has an account is sent to log in instead', function () {
    $invitation = invitationFor();

    User::factory()->create(['email' => 'lucy@acme.test']);

    $this->get(route('join.show', ['invitation' => $invitation->code]))
        ->assertRedirect(route('login'));

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => 'Someone Else',
        'password' => 'sixteen-char-password',
        'password_confirmation' => 'sixteen-char-password',
    ])->assertRedirect(route('login'));

    expect(User::where('email', 'lucy@acme.test')->count())->toBe(1);
    expect($invitation->fresh()->isAccepted())->toBeFalse();
})->note('A second account for one address would split the same person in two.');

test('an expired invitation cannot be joined', function () {
    $invitation = invitationFor();

    $invitation->update(['expires_at' => now()->subDay()]);

    $this->get(route('join.show', ['invitation' => $invitation->code]))
        ->assertRedirect(route('login'));

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => 'Lucy Alvarez',
        'password' => 'sixteen-char-password',
        'password_confirmation' => 'sixteen-char-password',
    ])->assertRedirect(route('login'));

    expect(User::where('email', 'lucy@acme.test')->exists())->toBeFalse();
});

test('an accepted invitation cannot be joined again', function () {
    $invitation = invitationFor();

    $invitation->update(['accepted_at' => now()]);

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => 'Lucy Alvarez',
        'password' => 'sixteen-char-password',
        'password_confirmation' => 'sixteen-char-password',
    ])->assertRedirect(route('login'));

    expect(User::where('email', 'lucy@acme.test')->exists())->toBeFalse();
});

test('an unknown code is a not found', function () {
    $this->get(route('join.show', ['invitation' => 'nonsense']))->assertNotFound();
});

test('a name and a confirmed password are required', function () {
    $invitation = invitationFor();

    $this->post(route('join.store', ['invitation' => $invitation->code]), [
        'name' => '',
        'password' => 'short',
        'password_confirmation' => 'mismatched',
    ])->assertSessionHasErrors(['name', 'password']);

    expect(User::where('email', 'lucy@acme.test')->exists())->toBeFalse();
});

test('a signed in user is not shown the join page', function () {
    $invitation = invitationFor();

    $this->actingAs(User::factory()->create())
        ->get(route('join.show', ['invitation' => $invitation->code]))
        ->assertRedirect();
})->note('The guest middleware keeps someone from joining while signed in as somebody else.');

test('the invitation email links to the join page', function () {
    $invitation = invitationFor();

    $mail = new App\Notifications\Organizations\OrganizationInvitation($invitation)
        ->toMail($invitation->inviter);

    expect($mail->actionUrl)->toBe(route('join.show', ['invitation' => $invitation->code]));
});
