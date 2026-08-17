<?php

use App\Ai\Tools\CreateTeam;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

test('an admin can create a team under the organization', function () {
    $result = new CreateTeam($this->admin)->handle(toolRequest(['name' => 'Design Ops']));

    expect($result)->toContain('Created the team Design Ops in NotaryDash, under the organization directly');

    $team = $this->organization->teams()->sole();

    expect($team->name)->toBe('Design Ops');
    expect($team->slug)->toBe('design-ops');
    expect($team->parent)->toBeInstanceOf(Organization::class);
});

test('a team can belong to a named client', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateTeam($this->admin)->handle(toolRequest([
        'name' => 'Acme Dev',
        'client' => 'Acme Title',
    ]));

    expect($result)->toContain('under the client Acme Title');
    expect($this->organization->teams()->sole()->parent)->toBeInstanceOf(Client::class);
});

test('the client name survives the phrasing the model relays', function (string $supplied) {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateTeam($this->admin)->handle(toolRequest([
        'name' => 'Acme Dev',
        'client' => $supplied,
    ]));

    expect($result)->toContain('under the client Acme Title');
})->with(['Acme Title', 'acme title', 'Acme Title.', 'the Acme Title client', 'Acme']);

test('an ambiguous client name creates nothing', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Escrow']);

    $result = new CreateTeam($this->admin)->handle(toolRequest([
        'name' => 'Acme Dev',
        'client' => 'Acme',
    ]));

    expect($result)
        ->toContain('More than one client')
        ->toContain('Acme Escrow, Acme Title');

    expect($this->organization->teams()->count())->toBe(0);
});

test('naming a client that does not exist creates nothing and lists the real ones', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new CreateTeam($this->admin)->handle(toolRequest([
        'name' => 'Wayne Dev',
        'client' => 'Wayne Enterprises',
    ]));

    expect($result)
        ->toContain('There is no client called Wayne Enterprises')
        ->toContain('Acme Title');

    expect($this->organization->teams()->count())->toBe(0);
});

test('an existing team is returned rather than duplicated', function (string $rephrased) {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Design Ops']);

    $result = new CreateTeam($this->admin)->handle(toolRequest(['name' => $rephrased]));

    expect($result)->toContain('already has a team called Design Ops');
    expect($this->organization->teams()->count())->toBe(1);
})->with(['Design Ops', 'design ops', 'Design Ops.', 'DESIGN  OPS']);

test('a member is refused and nothing is created', function () {
    $member = memberOf($this->organization, 'member');

    $result = new CreateTeam($member)->handle(toolRequest(['name' => 'Design Ops']));

    expect($result)
        ->toContain('does not have permission')
        ->toContain('Member');

    expect($this->organization->teams()->count())->toBe(0);
});

test('a client contact is refused', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $contact = contactFor($client, 'Lucy Client');

    $contact->switchOrganization($this->organization);

    $result = new CreateTeam($contact->refresh())->handle(toolRequest(['name' => 'Design Ops']));

    expect($result)->toContain('does not have permission');
    expect($this->organization->teams()->count())->toBe(0);
});

test('a team is never created in another organization', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);

    new CreateTeam($this->admin)->handle(toolRequest(['name' => 'Design Ops']));

    expect($this->organization->teams()->count())->toBe(1);
    expect($other->teams()->count())->toBe(0);
});

test('a client in another organization cannot hold the team', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);

    Client::factory()->heldBy($other)->create(['name' => 'Their Client']);

    $result = new CreateTeam($this->admin)->handle(toolRequest([
        'name' => 'Design Ops',
        'client' => 'Their Client',
    ]));

    expect($result)->toContain('There is no client called Their Client');
    expect($this->organization->teams()->count())->toBe(0);
});

test('the same team name in another organization is not a duplicate', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);

    Team::factory()->heldBy($other)->create(['name' => 'Design Ops']);

    new CreateTeam($this->admin)->handle(toolRequest(['name' => 'Design Ops']));

    expect($this->organization->teams()->sole()->name)->toBe('Design Ops');
});

test('a blank name asks rather than creating', function (string $name) {
    $result = new CreateTeam($this->admin)->handle(toolRequest(['name' => $name]));

    expect($result)->toContain('A team needs a name');
    expect($this->organization->teams()->count())->toBe(0);
})->with(['', '   ']);

test('a user with no organization creates nothing', function () {
    $stranger = User::factory()->create(['current_organization_id' => null]);

    $result = new CreateTeam($stranger)->handle(toolRequest(['name' => 'Design Ops']));

    expect($result)->toContain('not currently working in any organization');
    expect(Team::where('is_personal', false)->count())->toBe(0);
});

test('a removed member cannot create a team', function () {
    $this->organization->members()->detach($this->admin);

    $result = new CreateTeam($this->admin->refresh())->handle(toolRequest(['name' => 'Design Ops']));

    expect($result)->toContain('not currently working in any organization');
    expect($this->organization->teams()->count())->toBe(0);
});

test('the tool declares no organization argument', function () {
    $keys = array_keys(new CreateTeam($this->admin)->schema(new Illuminate\JsonSchema\JsonSchemaTypeFactory));

    expect($keys)->toBe(['name', 'client']);
});
