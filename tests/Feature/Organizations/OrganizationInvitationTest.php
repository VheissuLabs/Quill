<?php

use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

function pendingInvitationFor(Client $client, User $inviter, string $email): OrganizationInvitation
{
    return OrganizationInvitation::factory()->forClient($client)->create([
        'email' => $email,
        'invited_by' => $inviter->id,
    ]);
}

test('accepting an invitation creates the membership with its client and role', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $invited = User::factory()->create(['email' => 'lucy@acme.test']);
    $invitation = pendingInvitationFor($client, $owner, 'lucy@acme.test');

    $this->actingAs($invited)
        ->post(route('organization-invitations.accept', ['invitation' => $invitation->code]))
        ->assertRedirect();

    $invited->refresh();

    expect($invited->belongsToOrganization($organization))->toBeTrue();
    expect($invited->organizationRoleName($organization))->toBe(OrganizationRole::Client->value);
    expect($invited->isClientContact($organization))->toBeTrue();
    expect($organization->memberships()->where('user_id', $invited->id)->sole()->client_id)->toBe($client->id);
    expect($invitation->fresh()->isAccepted())->toBeTrue();
});

test('an accepted contact appears against the client they represent', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $invited = User::factory()->create(['name' => 'Lucy Alvarez', 'email' => 'lucy@acme.test']);
    $invitation = pendingInvitationFor($client, $owner, 'lucy@acme.test');

    expect($client->contacts()->count())->toBe(0);

    $this->actingAs($invited)
        ->post(route('organization-invitations.accept', ['invitation' => $invitation->code]))
        ->assertRedirect();

    expect($client->contacts()->with('user')->get()->pluck('user.name')->all())->toBe(['Lucy Alvarez']);
});

test('accepting puts the user into that organization', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create();

    $invited = User::factory()->create(['email' => 'lucy@acme.test']);
    $invitation = pendingInvitationFor($client, $owner, 'lucy@acme.test');

    $this->actingAs($invited)
        ->post(route('organization-invitations.accept', ['invitation' => $invitation->code]));

    expect($invited->refresh()->isCurrentOrganization($organization))->toBeTrue();
});

test('an invitation sent to someone else cannot be accepted', function () {
    $organization = Organization::factory()->create();
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create();

    $stranger = User::factory()->create(['email' => 'someone@else.test']);
    $invitation = pendingInvitationFor($client, $owner, 'lucy@acme.test');

    $this->actingAs($stranger)
        ->post(route('organization-invitations.accept', ['invitation' => $invitation->code]))
        ->assertSessionHasErrors('invitation');

    expect($stranger->refresh()->belongsToOrganization($organization))->toBeFalse();
    expect($invitation->fresh()->isAccepted())->toBeFalse();
});

test('an expired invitation cannot be accepted', function () {
    $organization = Organization::factory()->create();
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create();

    $invited = User::factory()->create(['email' => 'lucy@acme.test']);

    $invitation = OrganizationInvitation::factory()->forClient($client)->expired()->create([
        'email' => 'lucy@acme.test',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invited)
        ->post(route('organization-invitations.accept', ['invitation' => $invitation->code]))
        ->assertSessionHasErrors('invitation');

    expect($invited->refresh()->belongsToOrganization($organization))->toBeFalse();
});

test('an invitation cannot be accepted twice', function () {
    $organization = Organization::factory()->create();
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create();

    $invited = User::factory()->create(['email' => 'lucy@acme.test']);

    $invitation = OrganizationInvitation::factory()->forClient($client)->accepted()->create([
        'email' => 'lucy@acme.test',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invited)
        ->post(route('organization-invitations.accept', ['invitation' => $invitation->code]))
        ->assertSessionHasErrors('invitation');
});

test('a guest cannot accept an invitation', function () {
    $organization = Organization::factory()->create();
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create();

    $invitation = pendingInvitationFor($client, $owner, 'lucy@acme.test');

    $this->post(route('organization-invitations.accept', ['invitation' => $invitation->code]))
        ->assertRedirect(route('login'));
});

test('declining deletes the invitation and grants nothing', function () {
    $organization = Organization::factory()->create();
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create();

    $invited = User::factory()->create(['email' => 'lucy@acme.test']);
    $invitation = pendingInvitationFor($client, $owner, 'lucy@acme.test');

    $this->actingAs($invited)
        ->delete(route('organization-invitations.decline', ['invitation' => $invitation->code]))
        ->assertRedirect();

    expect(OrganizationInvitation::count())->toBe(0);
    expect($invited->refresh()->belongsToOrganization($organization))->toBeFalse();
});

test('someone else cannot decline an invitation', function () {
    $organization = Organization::factory()->create();
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create();

    $stranger = User::factory()->create(['email' => 'someone@else.test']);
    $invitation = pendingInvitationFor($client, $owner, 'lucy@acme.test');

    $this->actingAs($stranger)
        ->delete(route('organization-invitations.decline', ['invitation' => $invitation->code]))
        ->assertSessionHasErrors('invitation');

    expect(OrganizationInvitation::count())->toBe(1);
});

test('the dashboard shows a pending invitation to the invited person only', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $owner = memberOf($organization);
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $invited = memberOf(Organization::factory()->create(['name' => 'Their Own Org']));
    $invited->update(['email' => 'lucy@acme.test']);

    pendingInvitationFor($client, $owner, 'lucy@acme.test');

    $this->actingAs($invited->refresh())
        ->get(route('dashboard', ['current_team' => $invited->currentTeam?->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pendingOrganizationInvitations', 1)
            ->where('pendingOrganizationInvitations.0.organizationName', 'NotaryDash')
            ->where('pendingOrganizationInvitations.0.clientName', 'Acme Title'),
        );

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $owner->currentTeam?->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pendingOrganizationInvitations', []));
});
