<?php

use App\Ai\Tools\DescribeOrganization;
use App\Ai\Tools\ListClients;
use App\Ai\Tools\ListContacts;
use App\Ai\Tools\ListTeams;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Laravel\Ai\Tools\Request;

function toolRequest(): Request
{
    return new Request([]);
}

function memberOf(Organization $organization, string $role = 'owner'): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user, ['role' => $role]);
    $user->switchOrganization($organization);

    return $user->refresh();
}

test('describe_organization reports the organization and the asker role', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $user = memberOf($organization, 'admin');

    Client::factory()->for($organization)->create([
        'name' => 'Acme Title',
        'parent_type' => Organization::class,
        'parent_id' => $organization->id,
    ]);

    Team::factory()->for($organization)->create([
        'name' => 'Delivery',
        'parent_type' => Organization::class,
        'parent_id' => $organization->id,
    ]);

    $result = new DescribeOrganization($user)->handle(toolRequest());

    expect($result)
        ->toContain('NotaryDash')
        ->toContain('Admin')
        ->toContain('1 clients: Acme Title')
        ->toContain('1 teams: Delivery');
})->note('Names, not just counts: a bare number is all a model can relay if that is all it is given.');

test('list_clients says how each client is held', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $user = memberOf($organization);

    $team = Team::factory()->for($organization)->create([
        'name' => 'Delivery',
        'parent_type' => Organization::class,
        'parent_id' => $organization->id,
    ]);

    Client::factory()->for($organization)->create([
        'name' => 'Acme Title',
        'parent_type' => Team::class,
        'parent_id' => $team->id,
    ]);

    Client::factory()->for($organization)->create([
        'name' => 'Harbor Legal',
        'parent_type' => Organization::class,
        'parent_id' => $organization->id,
    ]);

    $result = new ListClients($user)->handle(toolRequest());

    expect($result)
        ->toContain('Acme Title (held by the team Delivery)')
        ->toContain('Harbor Legal (held by the organization directly)');
});

test('list_teams says what each team belongs to', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $user = memberOf($organization);

    $client = Client::factory()->for($organization)->create([
        'name' => 'Acme Title',
        'parent_type' => Organization::class,
        'parent_id' => $organization->id,
    ]);

    Team::factory()->for($organization)->create([
        'name' => 'Acme Dev',
        'parent_type' => Client::class,
        'parent_id' => $client->id,
    ]);

    Team::factory()->for($organization)->create([
        'name' => 'Delivery',
        'parent_type' => Organization::class,
        'parent_id' => $organization->id,
    ]);

    $result = new ListTeams($user)->handle(toolRequest());

    expect($result)
        ->toContain('Acme Dev (under the client Acme Title')
        ->toContain('Delivery (under the organization directly');
});

test('list_contacts distinguishes client contacts from staff', function () {
    $organization = Organization::factory()->create(['name' => 'NotaryDash']);
    $owner = memberOf($organization);

    $contact = User::factory()->create(['name' => 'Lucy Client', 'email' => 'lucy@acme.test']);
    $organization->members()->attach($contact, ['role' => 'client']);

    $result = new ListContacts($owner)->handle(toolRequest());

    expect($result)
        ->toContain('Lucy Client <lucy@acme.test> — Client (client contact)')
        ->toContain('works for the organization');
});

test('no read tool returns another organization data', function (string $tool) {
    $mine = Organization::factory()->create(['name' => 'NotaryDash']);
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    $user = memberOf($mine);

    Client::factory()->for($theirs)->create([
        'name' => 'Secret Client',
        'parent_type' => Organization::class,
        'parent_id' => $theirs->id,
    ]);

    Team::factory()->for($theirs)->create([
        'name' => 'Secret Team',
        'parent_type' => Organization::class,
        'parent_id' => $theirs->id,
    ]);

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
])->note('No tool takes an organization argument, so this is the whole tenant boundary.');

test('every read tool takes no arguments at all', function (string $tool) {
    $user = memberOf(Organization::factory()->create());

    expect(new $tool($user)->schema(new Illuminate\JsonSchema\JsonSchemaTypeFactory))->toBe([]);
})->with([
    DescribeOrganization::class,
    ListClients::class,
    ListTeams::class,
    ListContacts::class,
])->note('An organization parameter would be a cross-tenant read waiting to happen. There must be none.');

test('the tools follow the organization the user switches to', function () {
    $first = Organization::factory()->create(['name' => 'NotaryDash']);
    $second = Organization::factory()->create(['name' => '92 Labs']);

    $user = memberOf($first);
    $second->members()->attach($user, ['role' => 'member']);

    Client::factory()->for($second)->create([
        'name' => 'Second Org Client',
        'parent_type' => Organization::class,
        'parent_id' => $second->id,
    ]);

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
})->note('A blank tool result invites the model to fill the silence with invention.');

test('the agent grants all four read tools and no write tools', function () {
    $user = memberOf(Organization::factory()->create());

    $names = collect(new App\Ai\Agents\QuillAssistant($user)->tools())
        ->map(fn (object $tool) => $tool->name())
        ->all();

    expect($names)->toBe([
        'describe_organization',
        'list_clients',
        'list_teams',
        'list_contacts',
    ]);
})->note('Write tools arrive in later steps; this pins the grant so one does not appear early.');
