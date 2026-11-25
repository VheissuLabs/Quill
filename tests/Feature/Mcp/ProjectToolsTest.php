<?php

use App\Enums\ProjectVisibility;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Projects\CreateProjectTool;
use App\Mcp\Tools\Projects\DeleteProjectTool;
use App\Mcp\Tools\Projects\ShowProjectTool;
use App\Mcp\Tools\Projects\UpdateProjectTool;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

function projectToolMember(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization];
}

test('it shows a project by slug with its client and billing rate', function () {
    [$user, $organization] = projectToolMember();
    $client = Client::factory()->for($organization)->create(['name' => 'Globex']);
    $project = Project::factory()->for($organization)->public()->create(['name' => 'Site Rebuild', 'client_id' => $client->id, 'billing_rate' => 125]);

    QuillServer::actingAs($user)->tool(ShowProjectTool::class, ['project' => $project->slug])
        ->assertOk()
        ->assertSee('Site Rebuild')
        ->assertSee('Globex')
        ->assertSee('125.00');
});

test('private projects stay hidden from members outside their teams', function () {
    [$user, $organization] = projectToolMember('Member');
    $project = Project::factory()->for($organization)->create(['visibility' => ProjectVisibility::Private]);
    $project->teams()->attach(Team::factory()->for($organization)->create());

    QuillServer::actingAs($user)->tool(ShowProjectTool::class, ['project' => $project->id])->assertHasErrors();
});

test('it creates a private project for a team', function () {
    [$user, $organization] = projectToolMember();
    $team = Team::factory()->for($organization)->create();

    QuillServer::actingAs($user)->tool(CreateProjectTool::class, ['name' => 'Mobile App', 'visibility' => 'private', 'team_ids' => [$team->id]])
        ->assertOk()
        ->assertSee('Mobile App');

    $project = $organization->projects()->sole();

    expect($project->visibility)->toBe(ProjectVisibility::Private)
        ->and($project->teams()->pluck('teams.id')->all())->toBe([$team->id]);
});

test('creating a project uses the web validation and scopes clients to the organization', function () {
    [$user, $organization] = projectToolMember();
    $foreignClient = Client::factory()->create();

    QuillServer::actingAs($user)->tool(CreateProjectTool::class, ['name' => 'X', 'visibility' => 'secret'])->assertHasErrors(['visibility']);
    QuillServer::actingAs($user)->tool(CreateProjectTool::class, ['name' => 'X', 'visibility' => 'public', 'client_id' => $foreignClient->id])->assertHasErrors(['client id']);

    expect($organization->projects()->exists())->toBeFalse();
});

test('members without the manage projects permission cannot create projects', function () {
    [$user, $organization] = projectToolMember('Member');

    QuillServer::actingAs($user)->tool(CreateProjectTool::class, ['name' => 'Nope', 'visibility' => 'public'])->assertHasErrors();

    expect($organization->projects()->exists())->toBeFalse();
});

test('it updates only the fields given', function () {
    [$user, $organization] = projectToolMember();
    $project = Project::factory()->for($organization)->public()->create(['name' => 'Old', 'description' => 'Keep me']);

    QuillServer::actingAs($user)->tool(UpdateProjectTool::class, ['project' => $project->slug, 'name' => 'New'])->assertOk();

    expect($project->fresh())
        ->name->toBe('New')
        ->description->toBe('Keep me')
        ->visibility->toBe(ProjectVisibility::Public);
});

test('members cannot update or delete projects', function () {
    [$user, $organization] = projectToolMember('Member');
    $project = Project::factory()->for($organization)->public()->create(['name' => 'Old']);

    QuillServer::actingAs($user)->tool(UpdateProjectTool::class, ['project' => $project->id, 'name' => 'New'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeleteProjectTool::class, ['project' => $project->id])->assertHasErrors();

    expect($project->fresh()->name)->toBe('Old');
});

test('it deletes a project', function () {
    [$user, $organization] = projectToolMember();
    $project = Project::factory()->for($organization)->create();

    QuillServer::actingAs($user)->tool(DeleteProjectTool::class, ['project' => $project->id])->assertOk();

    expect(Project::query()->find($project->id))->toBeNull();
});

test('project tools cannot reach projects of another organization', function () {
    [$user] = projectToolMember();
    $foreign = Project::factory()->public()->create(['name' => 'Foreign']);

    QuillServer::actingAs($user)->tool(ShowProjectTool::class, ['project' => $foreign->id])->assertHasErrors();
    QuillServer::actingAs($user)->tool(UpdateProjectTool::class, ['project' => $foreign->id, 'name' => 'Hijacked'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeleteProjectTool::class, ['project' => $foreign->id])->assertHasErrors();

    expect($foreign->fresh()->name)->toBe('Foreign');
});
