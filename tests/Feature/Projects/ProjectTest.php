<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;

test('a project can be owned by a client', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    expect($project->owner)->toBeInstanceOf(Client::class);
    expect($project->owner->is($client))->toBeTrue();
    expect($project->organization_id)->toBe($organization->id);
    expect($project->slug)->toBe('acme-website');
});

test('a project can be owned by a team', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);

    $project = Project::factory()->ownedBy($team)->create(['name' => 'Harbor Rebuild']);

    expect($project->owner)->toBeInstanceOf(Team::class);
    expect($project->organization_id)->toBe($organization->id);
});

test('renaming a project moves its slug', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->heldBy($organization)->create();

    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $project->update(['name' => 'Acme Web Platform']);

    expect($project->fresh()->slug)->toBe('acme-web-platform');
});

test('an owner from another organization is refused', function () {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    $theirClient = Client::factory()->heldBy($theirs)->create(['name' => 'Their Client']);

    expect(fn () => Project::factory()->create([
        'organization_id' => $mine->id,
        'owner_type' => Client::class,
        'owner_id' => $theirClient->id,
        'name' => 'Sneaky',
    ]))->toThrow(RuntimeException::class, 'belongs to a different organization');

    expect(Project::count())->toBe(0);
})->note('The database cannot express a same-tenant rule across a morph, so it is checked on write.');

test('a project must have an owner', function () {
    $organization = Organization::factory()->create();

    expect(fn () => Project::create([
        'organization_id' => $organization->id,
        'name' => 'Ownerless',
    ]))->toThrow(RuntimeException::class, 'must be owned by');
});

test('only a client or a team may own a project', function () {
    $organization = Organization::factory()->create();

    expect(fn () => Project::create([
        'organization_id' => $organization->id,
        'owner_type' => Organization::class,
        'owner_id' => $organization->id,
        'name' => 'Org Owned',
    ]))->toThrow(RuntimeException::class, 'cannot own a project');
})->note('An organization-level project would have no client to attribute its issues to.');

test('a client points at where its work lands', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->heldBy($organization)->create();

    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $client->update(['default_project_id' => $project->id]);

    expect($client->fresh()->defaultProject->is($project))->toBeTrue();
    expect($project->defaultForClients->pluck('id')->all())->toBe([$client->id]);
});

test('a client may have no default project yet', function () {
    $client = Client::factory()->heldBy(Organization::factory()->create())->create();

    expect($client->default_project_id)->toBeNull();
})->note('A project may be owned by a client, so requiring one at creation would be circular.');

test('deleting the default project leaves the client without breaking it', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();

    $client->update(['default_project_id' => $project->id]);

    $project->forceDelete();

    expect($client->fresh()->default_project_id)->toBeNull();
});

test('projects are scoped to their organization', function () {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    Project::factory()->ownedBy(Client::factory()->heldBy($mine)->create())->create(['name' => 'Mine']);
    Project::factory()->ownedBy(Client::factory()->heldBy($theirs)->create())->create(['name' => 'Theirs']);

    expect($mine->projects()->pluck('name')->all())->toBe(['Mine']);
});

test('the seeder gives every organization projects with both kinds of owner', function () {
    $this->seed();

    $notaryDash = Organization::where('name', 'NotaryDash')->sole();

    expect($notaryDash->projects()->count())->toBeGreaterThanOrEqual(3);

    $owners = $notaryDash->projects()->with('owner')->get()
        ->map(fn (Project $project) => class_basename((string) $project->owner_type))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($owners)->toBe(['Client', 'Team']);
    expect(Client::whereNull('default_project_id')->pluck('name')->all())->toBe(['Sunbelt Signings']);
})->note('Both legal arrangements and the no-project case are visible without editing seeders.');

test('project activity is filed under the organization', function () {
    $organization = Organization::factory()->create();
    $client = Client::factory()->heldBy($organization)->create();

    App\Models\Activity::query()->delete();

    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);
    $project->update(['name' => 'Acme Web Platform']);

    $entries = App\Models\Activity::forOrganization($organization)->get();

    expect($entries)->toHaveCount(2);
    expect($entries->pluck('organization_id')->unique()->all())->toBe([$organization->id]);
})->note('A new subject the Activity hook does not know about silently logs with no organization and never appears.');

test('the seed makes the two kinds of ownership obvious', function () {
    $this->seed();

    $notaryDash = Organization::where('name', 'NotaryDash')->sole();

    $rows = $notaryDash->projects()->with(['owner', 'defaultForClients'])->orderBy('name')->get()
        ->map(fn (Project $project) => [
            'project' => $project->name,
            'owner' => class_basename((string) $project->owner_type).':'.$project->owner->name,
            'defaultFor' => $project->defaultForClients->pluck('name')->join(','),
        ])
        ->all();

    expect($rows)->toBe([
        ['project' => 'Acme Website', 'owner' => 'Client:Acme Title Co', 'defaultFor' => 'Acme Title Co'],
        ['project' => 'Delivery Internal Tooling', 'owner' => 'Team:Delivery', 'defaultFor' => ''],
        ['project' => 'Harbor Escrow Portal', 'owner' => 'Client:Harbor Escrow', 'defaultFor' => 'Harbor Escrow'],
    ]);
})->note('Every client project follows one rule, so the single team-owned project is the only thing that looks different.');

test('a client owns exactly the project its work lands in', function () {
    $this->seed();

    Client::whereNotNull('default_project_id')->with(['defaultProject.owner'])->get()
        ->each(function (Client $client) {
            expect($client->defaultProject->owner->is($client))->toBeTrue(
                "{$client->name} should default to a project it owns"
            );
        });

    expect(Client::whereNull('default_project_id')->pluck('name')->all())->toBe(['Sunbelt Signings']);
})->note('One client is deliberately unset: a client nobody has set up yet is a real state.');
