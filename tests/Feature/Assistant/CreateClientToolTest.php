<?php

use App\Ai\Tools\CreateClient;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;

test('an admin can create a client held by the organization', function () {
    $result = new CreateClient($this->admin)->handle(toolRequest(['name' => 'Wayne Enterprises']));

    expect($result)->toContain('Created the client Wayne Enterprises in NotaryDash, held by the organization directly');

    $client = $this->organization->clients()->sole();

    expect($client->name)->toBe('Wayne Enterprises');
    expect($client->slug)->toBe('wayne-enterprises');
    expect($client->parent)->toBeInstanceOf(Organization::class);
});

test('a client can be held by a named team', function () {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Delivery']);

    $result = new CreateClient($this->admin)->handle(toolRequest([
        'name' => 'Wayne Enterprises',
        'team' => 'Delivery',
    ]));

    expect($result)->toContain('held by the team Delivery');
    expect($this->organization->clients()->sole()->parent)->toBeInstanceOf(Team::class);
});

test('the team name is matched without regard to case', function () {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Delivery']);

    $result = new CreateClient($this->admin)->handle(toolRequest([
        'name' => 'Wayne Enterprises',
        'team' => 'delivery',
    ]));

    expect($result)->toContain('held by the team Delivery');
});

test('the word team in the name does not break the match', function (string $supplied) {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Development']);

    $result = new CreateClient($this->admin)->handle(toolRequest([
        'name' => 'Stark Industries',
        'team' => $supplied,
    ]));

    expect($result)->toContain('held by the team Development');
})->with(['Development', 'Development team', 'the Development Team', 'development']);

test('an ambiguous team name asks instead of guessing', function () {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Design']);
    Team::factory()->heldBy($this->organization)->create(['name' => 'Design Ops']);

    $result = new CreateClient($this->admin)->handle(toolRequest([
        'name' => 'Stark Industries',
        'team' => 'Design',
    ]));

    expect($result)->toContain('held by the team Design');
    expect($this->organization->clients()->count())->toBe(1);
});

test('a partial name matching two teams creates nothing', function () {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Design Ops']);
    Team::factory()->heldBy($this->organization)->create(['name' => 'Design Research']);

    $result = new CreateClient($this->admin)->handle(toolRequest([
        'name' => 'Stark Industries',
        'team' => 'Design',
    ]));

    expect($result)
        ->toContain('More than one team')
        ->toContain('Design Ops, Design Research');

    expect($this->organization->clients()->count())->toBe(0);
});

test('naming a team that does not exist creates nothing and lists the real ones', function () {
    Team::factory()->heldBy($this->organization)->create(['name' => 'Delivery']);
    Team::factory()->heldBy($this->organization)->create(['name' => 'Design']);

    $result = new CreateClient($this->admin)->handle(toolRequest([
        'name' => 'Wayne Enterprises',
        'team' => 'Marketing',
    ]));

    expect($result)
        ->toContain('There is no team called Marketing')
        ->toContain('Delivery, Design');

    expect($this->organization->clients()->count())->toBe(0);
});

test('a member is refused and nothing is created', function () {
    $member = memberOf($this->organization, 'member');

    $result = new CreateClient($member)->handle(toolRequest(['name' => 'Wayne Enterprises']));

    expect($result)
        ->toContain('does not have permission')
        ->toContain('Member')
        ->toContain('Nothing was changed');

    expect($this->organization->clients()->count())->toBe(0);
})->note('The assistant runs the same gate as a controller; it is not a way around the policy.');

test('a client contact is refused', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $contact = contactFor($client, 'Lucy Client');

    $contact->switchOrganization($this->organization);

    $result = new CreateClient($contact->refresh())->handle(toolRequest(['name' => 'Wayne Enterprises']));

    expect($result)->toContain('does not have permission');
    expect($this->organization->clients()->count())->toBe(1);
});

test('an existing client is returned rather than duplicated', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title Co']);

    $result = new CreateClient($this->admin)->handle(toolRequest(['name' => 'acme title co']));

    expect($result)->toContain('already has a client called Acme Title Co');
    expect($this->organization->clients()->count())->toBe(1);
});

test('a rephrased name is recognised as the same client', function (string $rephrased) {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title Co']);

    $result = new CreateClient($this->admin)->handle(toolRequest(['name' => $rephrased]));

    expect($result)->toContain('already has a client called Acme Title Co');
    expect($this->organization->clients()->count())->toBe(1);
})->with(['Acme Title Co.', 'acme title co', 'ACME TITLE CO', 'Acme  Title  Co', 'Acme Title Co!']);

test('the same client name in another organization is not a duplicate', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);

    Client::factory()->heldBy($other)->create(['name' => 'Acme Title Co']);

    new CreateClient($this->admin)->handle(toolRequest(['name' => 'Acme Title Co']));

    expect($this->organization->clients()->sole()->name)->toBe('Acme Title Co');
});

test('a client is never created in another organization', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);

    new CreateClient($this->admin)->handle(toolRequest(['name' => 'Wayne Enterprises']));

    expect($this->organization->clients()->count())->toBe(1);
    expect($other->clients()->count())->toBe(0);
});

test('a team in another organization cannot hold the client', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);

    Team::factory()->heldBy($other)->create(['name' => 'Their Team']);

    $result = new CreateClient($this->admin)->handle(toolRequest([
        'name' => 'Wayne Enterprises',
        'team' => 'Their Team',
    ]));

    expect($result)->toContain('There is no team called Their Team');
    expect($this->organization->clients()->count())->toBe(0);
});

test('a blank name asks rather than creating', function (mixed $name) {
    $result = new CreateClient($this->admin)->handle(toolRequest(['name' => $name]));

    expect($result)->toContain('A client needs a name');
    expect($this->organization->clients()->count())->toBe(0);
})->with(['', '   ']);

test('a user with no organization creates nothing', function () {
    $stranger = App\Models\User::factory()->create(['current_organization_id' => null]);

    $result = new CreateClient($stranger)->handle(toolRequest(['name' => 'Wayne Enterprises']));

    expect($result)->toContain('not currently working in any organization');
    expect(Client::count())->toBe(0);
});

test('the tool declares no organization argument', function () {
    $keys = array_keys(new CreateClient($this->admin)->schema(new Illuminate\JsonSchema\JsonSchemaTypeFactory));

    expect($keys)->toBe(['name', 'team']);
});
