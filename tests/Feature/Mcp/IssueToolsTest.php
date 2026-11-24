<?php

use App\Enums\IssueStatus;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Issues\CreateIssueTool;
use App\Mcp\Tools\Issues\ListIssuesTool;
use App\Mcp\Tools\Issues\UpdateIssueTool;
use App\Mcp\Tools\Organizations\ListOrganizationsTool;
use App\Mcp\Tools\Projects\ListProjectsTool;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function issueToolMember(string $role = 'Owner'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization, Project::factory()->for($organization)->create(['name' => 'Acme Site'])];
}

test('it creates an issue in a project named by slug', function () {
    [$user, , $project] = issueToolMember('Member');

    QuillServer::actingAs($user)->tool(CreateIssueTool::class, ['project' => $project->slug, 'title' => 'Fix the footer'])
        ->assertOk()
        ->assertSee('Fix the footer');

    expect($project->issues()->sole())->title->toBe('Fix the footer')->status->toBe(IssueStatus::Backlog)->created_by->toBe($user->id);
});

test('creating an issue uses the web validation', function () {
    [$user, , $project] = issueToolMember();

    QuillServer::actingAs($user)->tool(CreateIssueTool::class, ['project' => $project->id, 'title' => str_repeat('a', 300)])
        ->assertHasErrors(['title']);
});

test('it cannot create issues in another organization', function () {
    [$user] = issueToolMember();
    $foreign = Project::factory()->create();

    QuillServer::actingAs($user)->tool(CreateIssueTool::class, ['organization' => $foreign->organization->slug, 'project' => $foreign->slug, 'title' => 'Sneaky'])
        ->assertHasErrors();

    expect(Issue::query()->exists())->toBeFalse();
});

test('it updates an issue and marks it done', function () {
    [$user, , $project] = issueToolMember();
    $issue = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(UpdateIssueTool::class, ['issue_id' => $issue->id, 'title' => 'Renamed', 'status' => 'done'])->assertOk();

    expect($issue->fresh())->title->toBe('Renamed')->status->toBe(IssueStatus::Done);
});

test('it lists and searches issues', function () {
    [$user, , $project] = issueToolMember();
    Issue::factory()->for($project)->create(['title' => 'Login page']);
    Issue::factory()->for($project)->create(['title' => 'Invoice export']);

    QuillServer::actingAs($user)->tool(ListIssuesTool::class, ['search' => 'login'])
        ->assertOk()
        ->assertSee('Login page')
        ->assertDontSee('Invoice export');
});

test('it lists your organizations and projects', function () {
    [$user, $organization, $project] = issueToolMember();

    QuillServer::actingAs($user)->tool(ListOrganizationsTool::class)->assertOk()->assertSee($organization->slug);
    QuillServer::actingAs($user)->tool(ListProjectsTool::class)->assertOk()->assertSee('Acme Site');
});
