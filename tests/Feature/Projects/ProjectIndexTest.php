<?php

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;

/**
 * The index answers "my team's work": projects the current team owns, plus
 * client-level projects for the clients in that team's orbit.
 */
function projectIndexFor(App\Models\User $user): Illuminate\Testing\TestResponse
{
    return test()->actingAs($user)->get(route('projects.index'));
}

test('a team that holds clients sees its own work and theirs', function () {
    [$organization, $user] = organizationWith();

    $delivery = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);
    $acme = Client::factory()->heldBy($delivery)->create(['name' => 'Acme Title']);

    Project::factory()->ownedBy($delivery)->create(['name' => 'Harbor Rebuild']);
    Project::factory()->ownedBy($acme)->create(['name' => 'Acme Website']);

    $delivery->members()->attach($user, ['role' => 'admin']);
    $user->switchTeam($delivery);

    projectIndexFor($user->refresh())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('projects/Index')
            ->where('teamName', 'Delivery')
            ->has('projects', 2)
            ->where('projects.0.name', 'Acme Website')
            ->where('projects.0.ownerType', 'client')
            ->where('projects.1.name', 'Harbor Rebuild')
            ->where('projects.1.ownerType', 'team'),
        );
});

test('a team inside a client sees that client\'s projects', function () {
    [$organization, $user] = organizationWith();

    $acme = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);
    $acmeDev = Team::factory()->heldBy($acme)->create(['name' => 'Acme Dev']);

    Project::factory()->ownedBy($acme)->create(['name' => 'Acme Website']);

    $acmeDev->members()->attach($user, ['role' => 'admin']);
    $user->switchTeam($acmeDev);

    projectIndexFor($user->refresh())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('teamName', 'Acme Dev')
            ->has('projects', 1)
            ->where('projects.0.name', 'Acme Website'),
        );
})->note('A team under a client works on that client, so its projects are the team\'s work.');

test('another client\'s projects are not the team\'s work', function () {
    [$organization, $user] = organizationWith();

    $delivery = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);
    $acme = Client::factory()->heldBy($delivery)->create(['name' => 'Acme Title']);
    $elsewhere = Client::factory()->heldBy($organization)->create(['name' => 'Harbor Escrow']);

    Project::factory()->ownedBy($acme)->create(['name' => 'Acme Website']);
    Project::factory()->ownedBy($elsewhere)->create(['name' => 'Harbor Portal']);

    $delivery->members()->attach($user, ['role' => 'admin']);
    $user->switchTeam($delivery);

    projectIndexFor($user->refresh())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.name', 'Acme Website'),
        );
})->note('Harbor is held by the organization directly, not by Delivery.');

test('another team\'s own projects are excluded', function () {
    [$organization, $user] = organizationWith();

    $delivery = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);
    $design = Team::factory()->heldBy($organization)->create(['name' => 'Design']);

    Project::factory()->ownedBy($design)->create(['name' => 'Design System']);

    $delivery->members()->attach($user, ['role' => 'admin']);
    $user->switchTeam($delivery);

    projectIndexFor($user->refresh())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

test('a project from another organization never appears', function () {
    [$organization, $user] = organizationWith();
    $theirs = Organization::factory()->create(['name' => '92 Labs']);

    $delivery = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);
    Project::factory()->ownedBy(Client::factory()->heldBy($theirs)->create())->create(['name' => 'Theirs']);

    $delivery->members()->attach($user, ['role' => 'admin']);
    $user->switchTeam($delivery);

    projectIndexFor($user->refresh())
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects', 0));
});

test('a personal team has no projects and says so', function () {
    [, $user] = organizationWith();

    projectIndexFor($user)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 0)
            ->where('teamName', null),
        );
})->note('A personal team belongs to no organization, so there is no team scope to read.');

test('the table carries what each row shows', function () {
    [$organization, $user] = organizationWith();

    $delivery = Team::factory()->heldBy($organization)->create(['name' => 'Delivery']);
    $acme = Client::factory()->heldBy($delivery)->create(['name' => 'Acme Title']);
    $project = Project::factory()->ownedBy($acme)->create(['name' => 'Acme Website']);

    $acme->update(['default_project_id' => $project->id]);

    $delivery->members()->attach($user, ['role' => 'admin']);
    $user->switchTeam($delivery);

    projectIndexFor($user->refresh())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('projects.0.ownerName', 'Acme Title')
            ->where('projects.0.slug', 'acme-website')
            ->has('projects.0.createdAt'),
        );
});

test('a guest cannot open the index', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

test('a client contact cannot open the index', function () {
    [$organization] = organizationWith();
    $client = Client::factory()->heldBy($organization)->create(['name' => 'Acme Title']);

    $contact = contactFor($client, 'Lucy Client');
    $contact->switchOrganization($organization);

    $this->actingAs($contact->refresh())
        ->get(route('projects.index'))
        ->assertForbidden();
});
