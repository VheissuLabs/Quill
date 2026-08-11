<?php

use App\Ai\Tools\RenameClient;
use App\Ai\Tools\RenameTeam;
use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Team;

test('a client can be renamed and its slug follows', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'new_name' => 'Acme Title Co',
    ]));

    expect($result)->toContain('Renamed the client Acme Title to Acme Title Co');
    expect($client->fresh()->name)->toBe('Acme Title Co');
    expect($client->fresh()->slug)->toBe('acme-title-co');
});

test('a team can be renamed and its slug follows', function () {
    $team = Team::factory()->heldBy($this->organization)->create(['name' => 'Design']);

    $result = new RenameTeam($this->admin)->handle(toolRequest([
        'team' => 'Design',
        'new_name' => 'Design Ops',
    ]));

    expect($result)->toContain('Renamed the team Design to Design Ops');
    expect($team->fresh()->name)->toBe('Design Ops');
    expect($team->fresh()->slug)->toBe('design-ops');
});

test('the target survives the phrasing the model relays', function (string $supplied) {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => $supplied,
        'new_name' => 'Acme Title Co',
    ]));

    expect($result)->toContain('Renamed the client Acme Title');
})->with(['Acme Title', 'acme title', 'the Acme Title client', 'Acme Title.']);

test('renaming to the name it already has changes nothing', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $slug = $client->slug;

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'new_name' => 'acme title.',
    ]));

    expect($result)->toContain('is already called that');
    expect($client->fresh()->slug)->toBe($slug);
})->note('A no-op rename must not churn the slug and break existing links.');

test('renaming onto an existing name is refused', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    Client::factory()->heldBy($this->organization)->create(['name' => 'Harbor Escrow']);

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'new_name' => 'Harbor Escrow',
    ]));

    expect($result)->toContain('already has a client called Harbor Escrow');
    expect($this->organization->clients()->where('name', 'Acme Title')->exists())->toBeTrue();
});

test('an ambiguous target renames nothing', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Escrow']);

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => 'Acme',
        'new_name' => 'Renamed',
    ]));

    expect($result)->toContain('More than one client matches');
    expect($this->organization->clients()->where('name', 'Renamed')->exists())->toBeFalse();
});

test('a target that does not exist renames nothing and lists the real ones', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => 'Wayne Enterprises',
        'new_name' => 'Renamed',
    ]));

    expect($result)
        ->toContain('There is no client called Wayne Enterprises')
        ->toContain('Acme Title');
});

test('a blank new name asks rather than renaming', function (string $newName) {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => 'Acme Title',
        'new_name' => $newName,
    ]));

    expect($result)->toContain('A new name is needed');
    expect($this->organization->clients()->sole()->name)->toBe('Acme Title');
})->with(['', '  ']);

test('a member is refused and nothing changes', function () {
    $member = memberOf($this->organization, OrganizationRole::Member);

    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $result = new RenameClient($member)->handle(toolRequest([
        'client' => 'Acme Title',
        'new_name' => 'Renamed',
    ]));

    expect($result)->toContain('does not have permission');
    expect($this->organization->clients()->sole()->name)->toBe('Acme Title');
});

test('a client in another organization cannot be renamed', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);
    $theirs = Client::factory()->heldBy($other)->create(['name' => 'Their Client']);

    $result = new RenameClient($this->admin)->handle(toolRequest([
        'client' => 'Their Client',
        'new_name' => 'Mine Now',
    ]));

    expect($result)->toContain('There is no client called Their Client');
    expect($theirs->fresh()->name)->toBe('Their Client');
});

test('a removed member cannot rename anything', function () {
    Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    $this->organization->members()->detach($this->admin);

    $result = new RenameClient($this->admin->refresh())->handle(toolRequest([
        'client' => 'Acme Title',
        'new_name' => 'Renamed',
    ]));

    expect($result)->toContain('not currently working in any organization');
    expect($this->organization->clients()->sole()->name)->toBe('Acme Title');
});

test('the rename tools declare only a target and a new name', function () {
    $schema = new Illuminate\JsonSchema\JsonSchemaTypeFactory;

    expect(array_keys(new RenameClient($this->admin)->schema($schema)))->toBe(['client', 'new_name']);
    expect(array_keys(new RenameTeam($this->admin)->schema($schema)))->toBe(['team', 'new_name']);
})->note('No delete tool exists at all, and rename cannot touch anything but the name.');
