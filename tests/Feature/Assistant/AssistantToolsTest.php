<?php

use App\Ai\Tools\DescribeOrganization;
use App\Ai\Tools\ListClients;
use App\Ai\Tools\ListContacts;
use App\Ai\Tools\ListTeams;
use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

test('describe_organization reports the organization and the asker role', function () {
    [$organization, $user] = organizationWith(OrganizationRole::Admin);

    Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);

    $result = new DescribeOrganization($user)->handle(toolRequest());

    expect($result)
        ->toContain('NotaryDash')
        ->toContain('Admin')
        ->toContain('1 clients: Acme Title')
        ->toContain('1 teams: Delivery');
});

test('list_clients says how each client is held', function () {
    [$organization, $user] = organizationWith();

    $team = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);

    Client::factory()->heldBy($team)->create(['name' => 'Acme Title']);

    Client::factory()->heldBy($organization)->create(['name' => 'Harbor Legal']);

    $result = new ListClients($user)->handle(toolRequest());

    expect($result)
        ->toContain('Acme Title (held by the team Delivery)')
        ->toContain('Harbor Legal (held by the organization directly)');
});

test('list_teams says what each team belongs to', function () {
    [$organization, $user] = organizationWith();

    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    Team::factory()->heldBy($client)->create(['name' => 'Acme Dev']);

    Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);

    $result = new ListTeams($user)->handle(toolRequest());

    expect($result)
        ->toContain('Acme Dev (under the client Acme Title')
        ->toContain('Delivery (under the organization directly');
});

test('list_contacts names the client each contact represents', function () {
    [$organization, $owner] = organizationWith();

    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    contactFor($client, 'Lucy Client', 'lucy@acme.test');

    $result = new ListContacts($owner)->handle(toolRequest());

    expect($result)
        ->toContain('Lucy Client <lucy@acme.test> — Client, contact for the client Acme Title')
        ->toContain('works for the organization');
});

test('list_clients names the contacts at each client', function () {
    [$organization, $owner] = organizationWith();

    $withContacts = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    Client::factory()->heldBy($organization)->create(['name' => 'Harbor Escrow']);

    contactFor($withContacts, 'Lucy Client', 'lucy@acme.test');

    $result = new ListClients($owner)->handle(toolRequest());

    expect($result)
        ->toContain('Acme Title (held by the organization directly). Contacts: Lucy Client <lucy@acme.test>')
        ->toContain('Harbor Escrow (held by the organization directly). Contacts: none yet');
});

test('a contact at one client is not reported against another', function () {
    [$organization, $owner] = organizationWith();

    $acme = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $harbor = Client::factory()->heldBy($organization)->create(['name' => 'Harbor Escrow']);

    contactFor($acme, 'Lucy Acme');

    expect($acme->contacts()->with('user')->get()->pluck('user.name')->all())->toBe(['Lucy Acme']);
    expect($harbor->contacts()->count())->toBe(0);
});

test('staff are never counted as a client contact', function () {
    [$organization, $owner] = organizationWith();

    $client = Client::factory()->heldBy($organization)->create();

    expect($client->contacts()->count())->toBe(0);
});

test('no read tool returns another organization data', function (string $tool) {
    $mine = Organization::factory()->create(['name' => 'NotaryDash']);
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    $user = memberOf($mine);

    Client::factory()->heldBy($theirs)->create(['name' => 'Secret Client']);

    Team::factory()->heldBy($theirs)->create(['name' => 'Secret Team']);

    $stranger = User::factory()->create(['name' => 'Secret Person', 'email' => 'secret@92labs.test']);
    $theirs->members()->attach($stranger, ['role' => 'member']);

    $result = new $tool($user)->handle(toolRequest());

    expect($result)
        ->toContain('NotaryDash')
        ->not->toContain('92 Labs')
        ->not->toContain('Secret Client')
        ->not->toContain('Secret Team')
        ->not->toContain('Secret Person');
})->with([
    DescribeOrganization::class,
    ListClients::class,
    ListTeams::class,
    ListContacts::class,
]);

test('every read tool takes no arguments at all', function (string $tool) {
    $user = memberOf(Organization::factory()->create());

    expect(new $tool($user)->schema(new Illuminate\JsonSchema\JsonSchemaTypeFactory))->toBe([]);
})->with([
    DescribeOrganization::class,
    ListClients::class,
    ListTeams::class,
    ListContacts::class,
]);

test('the tools follow the organization the user switches to', function () {
    $first = Organization::factory()->create(['name' => 'NotaryDash']);
    $second = Organization::factory()->create(['name' => '92 Labs']);

    $user = memberOf($first);
    $second->members()->attach($user, ['role' => 'member']);

    Client::factory()->heldBy($second)->create(['name' => 'Second Org Client']);

    expect(new ListClients($user)->handle(toolRequest()))->not->toContain('Second Org Client');

    $user->switchOrganization($second);

    expect(new ListClients($user->refresh())->handle(toolRequest()))->toContain('Second Org Client');
});

test('a user with no current organization gets a plain explanation, not an error', function (string $tool) {
    $user = User::factory()->create(['current_organization_id' => null]);

    expect(new $tool($user)->handle(toolRequest()))
        ->toContain('not currently working in any organization');
})->with([
    DescribeOrganization::class,
    ListClients::class,
    ListTeams::class,
    ListContacts::class,
]);

test('an empty organization says so rather than returning nothing', function () {
    $organization = Organization::factory()->create(['name' => 'Fresh Org']);
    $user = memberOf($organization);

    expect(new ListClients($user)->handle(toolRequest()))->toBe('Fresh Org has no clients yet.');
    expect(new ListTeams($user)->handle(toolRequest()))->toBe('Fresh Org has no teams yet.');
});

test('the agent grants exactly the tools built so far', function () {
    $user = memberOf(Organization::factory()->create());

    $names = collect(new App\Ai\Agents\QuillAssistant($user)->tools())
        ->map(fn (object $tool) => $tool->name())
        ->all();

    expect($names)->toBe([
        'describe_organization',
        'list_clients',
        'list_teams',
        'list_contacts',
        'create_client',
    ]);
});

test('a removed member can no longer read the organization', function (string $tool) {
    [$organization, $user] = organizationWith(OrganizationRole::Admin);

    Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $organization->members()->detach($user);

    expect(new $tool($user->refresh())->handle(toolRequest()))
        ->toContain('not currently working in any organization')
        ->not->toContain('Acme Title');
})->with([
    DescribeOrganization::class,
    ListClients::class,
    ListTeams::class,
    ListContacts::class,
]);

test('a removed member cannot create a client', function () {
    [$organization, $user] = organizationWith(OrganizationRole::Admin);

    $organization->members()->detach($user);

    $result = new App\Ai\Tools\CreateClient($user->refresh())->handle(toolRequest(['name' => 'Wayne Enterprises']));

    expect($result)->toContain('not currently working in any organization');
    expect($organization->clients()->count())->toBe(0);
});
