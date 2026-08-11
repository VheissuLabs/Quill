<?php

use App\Ai\Tools\CreateContact;
use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\Organizations\OrganizationInvitation as InvitationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
});

test('inviting an unknown email creates a pending invitation and mails it', function () {
    $result = new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)->toContain('Invited lucy@acme.test as a contact for Acme Title');

    $invitation = OrganizationInvitation::sole();

    expect($invitation->email)->toBe('lucy@acme.test');
    expect($invitation->client_id)->toBe($this->client->id);
    expect($invitation->role)->toBe(OrganizationRole::Client);
    expect($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(
        InvitationNotification::class,
        fn (InvitationNotification $notification) => $notification->inApp === false,
    );
});

test('no user and no membership exist until the invitation is accepted', function () {
    new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'lucy@acme.test',
    ]));

    expect(User::where('email', 'lucy@acme.test')->exists())->toBeFalse();
    expect($this->client->contacts()->count())->toBe(0);
});

test('inviting someone already using Quill notifies them in the app instead', function () {
    $existing = User::factory()->create(['email' => 'jen@notarydash.com']);

    new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'jen@notarydash.com',
    ]));

    Notification::assertSentTo(
        $existing,
        InvitationNotification::class,
        fn (InvitationNotification $notification) => $notification->inApp === true,
    );
});

test('the in-app notification carries what the sidebar needs', function () {
    $existing = User::factory()->create(['email' => 'jen@notarydash.com']);

    new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'jen@notarydash.com',
    ]));

    Notification::assertSentTo($existing, InvitationNotification::class, function (InvitationNotification $notification) use ($existing) {
        $payload = $notification->toArray($existing);

        expect($payload['title'])->toContain('as a contact for Acme Title');
        expect($payload['organization_name'])->toBe('NotaryDash');

        return true;
    });
});

test('inviting the same email twice re-sends rather than stacking invitations', function () {
    $arguments = ['client' => 'Acme Title', 'email' => 'lucy@acme.test'];

    new CreateContact($this->admin)->handle(toolRequest($arguments));
    $second = new CreateContact($this->admin)->handle(toolRequest($arguments));

    expect($second)->toContain('already had a pending invitation, so it was sent again');
    expect(OrganizationInvitation::count())->toBe(1);

    Notification::assertSentOnDemandTimes(InvitationNotification::class, 2);
});

test('the email address is normalised so case is not a second invitation', function () {
    new CreateContact($this->admin)->handle(toolRequest(['client' => 'Acme Title', 'email' => 'Lucy@Acme.test']));
    new CreateContact($this->admin)->handle(toolRequest(['client' => 'Acme Title', 'email' => 'lucy@acme.test']));

    expect(OrganizationInvitation::count())->toBe(1);
    expect(OrganizationInvitation::sole()->email)->toBe('lucy@acme.test');
});

test('a missing email asks rather than inventing one', function (string $email) {
    $result = new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => $email,
    ]));

    expect($result)->toContain('needs an email address');
    expect(OrganizationInvitation::count())->toBe(0);
})->with(['', '   ']);

test('a malformed email invites nobody', function () {
    $result = new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'not-an-email',
    ]));

    expect($result)->toContain('is not a valid email address');
    expect(OrganizationInvitation::count())->toBe(0);
});

test('a missing client asks which one and lists them', function () {
    $result = new CreateContact($this->admin)->handle(toolRequest([
        'client' => '',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)
        ->toContain('has to belong to a client')
        ->toContain('Acme Title');

    expect(OrganizationInvitation::count())->toBe(0);
});

test('a client that does not exist invites nobody', function () {
    $result = new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Wayne Enterprises',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)->toContain('There is no client called Wayne Enterprises');
    expect(OrganizationInvitation::count())->toBe(0);
});

test('an ambiguous client invites nobody', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Escrow']);

    $result = new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Acme',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)->toContain('More than one client');
    expect(OrganizationInvitation::count())->toBe(0);
});

test('a member is refused', function () {
    $member = memberOf($this->organization, OrganizationRole::Member);

    $result = new CreateContact($member)->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)->toContain('does not have permission');
    expect(OrganizationInvitation::count())->toBe(0);
});

test('a client contact cannot invite other contacts', function () {
    $contact = contactFor($this->client, 'Existing Contact');

    $contact->switchOrganization($this->organization);

    $result = new CreateContact($contact->refresh())->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)->toContain('does not have permission');
    expect(OrganizationInvitation::count())->toBe(0);
});

test('a client in another organization cannot be used', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);

    Client::factory()->heldBy($other)->create(['name' => 'Their Client']);

    $result = new CreateContact($this->admin)->handle(toolRequest([
        'client' => 'Their Client',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)->toContain('There is no client called Their Client');
    expect(OrganizationInvitation::count())->toBe(0);
});

test('a removed member cannot invite anyone', function () {
    $this->organization->members()->detach($this->admin);

    $result = new CreateContact($this->admin->refresh())->handle(toolRequest([
        'client' => 'Acme Title',
        'email' => 'lucy@acme.test',
    ]));

    expect($result)->toContain('not currently working in any organization');
    expect(OrganizationInvitation::count())->toBe(0);
});

test('the tool declares no organization argument', function () {
    $keys = array_keys(new CreateContact($this->admin)->schema(new Illuminate\JsonSchema\JsonSchemaTypeFactory));

    expect($keys)->toBe(['client', 'email', 'name']);
});
