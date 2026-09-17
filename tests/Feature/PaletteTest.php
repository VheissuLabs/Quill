<?php

use App\Enums\ProjectVisibility;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;

function palette(Organization $organization, string $term): string
{
    return route('palette.search', ['current_organization' => $organization, 'q' => $term]);
}

test('a member searches every record type at once', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['name' => 'Orbit web']);
    Issue::factory()->for($project)->create(['title' => 'Orbit drifts on resize', 'number' => 12]);
    Feature::factory()->for($project)->create(['title' => 'Orbit dark mode']);
    Team::factory()->for($organization)->create(['name' => 'Orbit crew']);
    Client::factory()->for($organization)->create(['name' => 'Orbit Industries']);
    Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create(['name' => 'Orbit sprint 3']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'orbit'));

    $response->assertOk();

    expect(collect($response->json())->pluck('type')->unique()->sort()->values()->all())
        ->toBe(['client', 'feature', 'issue', 'project', 'sprint', 'team']);
});

test('an issue is found by its number as well as its title', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    Issue::factory()->for($project)->create(['title' => 'Nothing alike', 'number' => 142]);

    $response = $this->actingAs($member)->getJson(palette($organization, '142'));

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.type'))->toBe('issue')
        ->and($response->json('0.title'))->toContain('Nothing alike');
});

test('projects the member cannot see are left out, along with their issues and features', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $hidden = Project::factory()->for($organization)->create([
        'name' => 'Secret ledger',
        'visibility' => ProjectVisibility::Private,
    ]);
    $hidden->teams()->attach(Team::factory()->for($organization)->create());
    Issue::factory()->for($hidden)->create(['title' => 'Secret regression']);
    Feature::factory()->for($hidden)->create(['title' => 'Secret export']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'secret'));

    $response->assertOk();
    expect($response->json())->toBeEmpty();
});

test('a disabled capability removes its records from the results', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $organization->capabilitySettings()->create([
        'module' => 'development',
        'capability' => 'issues',
        'enabled' => false,
    ]);
    $project = Project::factory()->for($organization)->create(['name' => 'Beacon']);
    Issue::factory()->for($project)->create(['title' => 'Beacon flickers']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'beacon'));

    expect(collect($response->json())->pluck('type')->all())->not->toContain('issue');
});

test('records belonging to another organization never appear', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->create(['name' => 'Foreign atlas']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'atlas'));

    expect($response->json())->toBeEmpty();
});

test('a non member cannot search an organization', function () {
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($outsider)->getJson(palette($organization, 'anything'))->assertForbidden();
});

test('a guest cannot search at all', function () {
    $organization = Organization::factory()->create();

    $this->getJson(palette($organization, 'anything'))->assertUnauthorized();
});

test('a term shorter than two characters returns nothing', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->for($organization)->create(['name' => 'Atlas']);

    $response = $this->actingAs($member)->getJson(palette($organization, 'a'));

    $response->assertOk();
    expect($response->json())->toBeEmpty();
});

test('each record type is capped so one type cannot flood the palette', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['name' => 'Cap test']);
    Issue::factory()->for($project)->count(12)->sequence(fn ($sequence) => [
        'title' => "Cap issue {$sequence->index}",
        'number' => $sequence->index + 1,
    ])->create();

    $response = $this->actingAs($member)->getJson(palette($organization, 'cap'));

    expect(collect($response->json())->where('type', 'issue'))->toHaveCount(5);
});

test('the options endpoint carries what the create modals need', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    Project::factory()->for($organization)->create(['name' => 'Atlas']);

    $response = $this->actingAs($member)
        ->getJson(route('palette.options', ['current_organization' => $organization]));

    $response->assertOk()
        ->assertJsonStructure([
            'issue' => ['projects', 'teams', 'sprints', 'members', 'statuses', 'priorities'],
            'visibilities',
        ]);
});

test('the options endpoint withholds issue options when the capability is off', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $organization->capabilitySettings()->create([
        'module' => 'development',
        'capability' => 'issues',
        'enabled' => false,
    ]);

    $response = $this->actingAs($member)
        ->getJson(route('palette.options', ['current_organization' => $organization]));

    $response->assertOk();
    expect($response->json('issue'))->toBeNull();
});

test('a non member cannot read palette options', function () {
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();

    $this->actingAs($outsider)
        ->getJson(route('palette.options', ['current_organization' => $organization]))
        ->assertForbidden();
});
