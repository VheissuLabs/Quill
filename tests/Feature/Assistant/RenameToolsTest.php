<?php

use App\Ai\Tools\RenameClient;
use App\Ai\Tools\RenameProject;
use App\Ai\Tools\RenameTeam;
use App\Enums\OrganizationRole;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
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

test('a project can be renamed and its slug follows', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $result = new RenameProject($this->admin)->handle(toolRequest([
        'project' => 'Acme Website',
        'new_name' => 'Acme Storefront',
    ]));

    expect($result)->toContain('Renamed the project Acme Website to Acme Storefront');
    expect($project->fresh()->name)->toBe('Acme Storefront');
    expect($project->fresh()->slug)->toBe('acme-storefront');
});

test('renaming a project leaves its owner alone', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    new RenameProject($this->admin)->handle(toolRequest([
        'project' => 'Acme Website',
        'new_name' => 'Acme Storefront',
    ]));

    expect($project->fresh()->owner->is($client))->toBeTrue();
})->note('Rename is the only project write besides create — it must not become a way to reassign ownership.');

test('renaming a project onto an existing name is refused', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);
    Project::factory()->ownedBy($client)->create(['name' => 'Acme Portal']);

    $result = new RenameProject($this->admin)->handle(toolRequest([
        'project' => 'Acme Website',
        'new_name' => 'Acme Portal',
    ]));

    expect($result)->toContain('already has a project called Acme Portal');
    expect($this->organization->projects()->where('name', 'Acme Website')->exists())->toBeTrue();
});

test('a project that does not exist renames nothing and lists the real ones', function () {
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $result = new RenameProject($this->admin)->handle(toolRequest([
        'project' => 'Wayne Portal',
        'new_name' => 'Renamed',
    ]));

    expect($result)
        ->toContain('There is no project called Wayne Portal')
        ->toContain('Acme Website');
});

test('a project in another organization cannot be renamed', function () {
    $other = Organization::factory()->create(['name' => '92 Labs']);
    $theirs = Project::factory()
        ->ownedBy(Client::factory()->heldBy($other)->create())
        ->create(['name' => 'Their Project']);

    $result = new RenameProject($this->admin)->handle(toolRequest([
        'project' => 'Their Project',
        'new_name' => 'Mine Now',
    ]));

    expect($result)->toContain('There is no project called Their Project');
    expect($theirs->fresh()->name)->toBe('Their Project');
});

test('a member is refused and no project is renamed', function () {
    $member = memberOf($this->organization, OrganizationRole::Member);
    $client = Client::factory()->heldBy($this->organization)->create(['name' => 'Acme Title']);

    Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $result = new RenameProject($member)->handle(toolRequest([
        'project' => 'Acme Website',
        'new_name' => 'Renamed',
    ]));

    expect($result)->toContain('does not have permission');
    expect($this->organization->projects()->sole()->name)->toBe('Acme Website');
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
    expect(array_keys(new RenameProject($this->admin)->schema($schema)))->toBe(['project', 'new_name']);
})->note('No delete tool exists at all, and rename cannot touch anything but the name.');
