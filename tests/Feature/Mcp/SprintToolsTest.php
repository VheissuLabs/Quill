<?php

use App\Enums\IssueStatus;
use App\Enums\SprintStatus;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Sprints\CompleteSprintTool;
use App\Mcp\Tools\Sprints\CreateSprintTool;
use App\Mcp\Tools\Sprints\DeleteSprintTool;
use App\Mcp\Tools\Sprints\ListSprintsTool;
use App\Mcp\Tools\Sprints\MoveSprintIssueTool;
use App\Mcp\Tools\Sprints\ShowSprintTool;
use App\Mcp\Tools\Sprints\StartSprintTool;
use App\Mcp\Tools\Sprints\UpdateSprintTool;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;

function sprintToolMember(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);
    $team = Team::factory()->for($organization)->create();
    $team->members()->attach($user);

    return [$user, $organization, $team, Project::factory()->for($organization)->create()];
}

test('it lists the sprints on your board', function () {
    [$user, , $team] = sprintToolMember('Member');
    Sprint::factory()->for($team, 'owner')->create(['name' => 'Sprint Alpha']);
    Sprint::factory()->create(['name' => 'Elsewhere']);

    QuillServer::actingAs($user)->tool(ListSprintsTool::class)
        ->assertOk()
        ->assertSee('Sprint Alpha')
        ->assertDontSee('Elsewhere');
});

test('it shows a sprint with its columns and issues', function () {
    [$user, , $team, $project] = sprintToolMember('Member');
    $sprint = Sprint::factory()->for($team, 'owner')->create();
    Issue::factory()->for($project)->create(['title' => 'Planned work', 'sprint_id' => $sprint->id]);

    QuillServer::actingAs($user)->tool(ShowSprintTool::class, ['sprint_id' => $sprint->id])
        ->assertOk()
        ->assertSee(['Planned work', 'In progress']);
});

test('it does not show a sprint from another organization', function () {
    [$user] = sprintToolMember();
    $foreign = Sprint::factory()->create();

    QuillServer::actingAs($user)->tool(ShowSprintTool::class, ['sprint_id' => $foreign->id])->assertHasErrors();
});

test('admins create sprints on their current team board', function () {
    [$user, , $team] = sprintToolMember();

    QuillServer::actingAs($user)->tool(CreateSprintTool::class, ['name' => 'Sprint 7', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-14'])
        ->assertOk()
        ->assertSee('Sprint 7');

    expect(Sprint::sole())->owner->is($team)->toBeTrue()->status->toBe(SprintStatus::Planned);
});

test('creating a sprint uses the web validation and permissions', function () {
    [$admin] = sprintToolMember();
    [$member] = sprintToolMember('Member');

    QuillServer::actingAs($admin)->tool(CreateSprintTool::class, ['name' => 'Backwards', 'starts_at' => '2026-12-14', 'ends_at' => '2026-12-01'])
        ->assertHasErrors(['ends at']);
    QuillServer::actingAs($member)->tool(CreateSprintTool::class, ['name' => 'Sprint 7', 'starts_at' => '2026-12-01', 'ends_at' => '2026-12-14'])
        ->assertHasErrors();

    expect(Sprint::exists())->toBeFalse();
});

test('it updates only the sprint fields given', function () {
    [$user, , $team] = sprintToolMember();
    $sprint = Sprint::factory()->for($team, 'owner')->create(['starts_at' => '2026-12-01', 'ends_at' => '2026-12-14']);

    QuillServer::actingAs($user)->tool(UpdateSprintTool::class, ['sprint_id' => $sprint->id, 'name' => 'Holiday sprint'])->assertOk();

    expect($sprint->fresh())->name->toBe('Holiday sprint')->ends_at->toDateString()->toBe('2026-12-14');
});

test('members cannot update or delete sprints', function () {
    [$user, , $team] = sprintToolMember('Member');
    $sprint = Sprint::factory()->for($team, 'owner')->create(['name' => 'Kept']);

    QuillServer::actingAs($user)->tool(UpdateSprintTool::class, ['sprint_id' => $sprint->id, 'name' => 'Changed'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeleteSprintTool::class, ['sprint_id' => $sprint->id])->assertHasErrors();
    QuillServer::actingAs($user)->tool(StartSprintTool::class, ['sprint_id' => $sprint->id])->assertHasErrors();

    expect($sprint->fresh())->name->toBe('Kept')->status->toBe(SprintStatus::Planned);
});

test('deleting a sprint returns its issues to the backlog', function () {
    [$user, , $team, $project] = sprintToolMember();
    $sprint = Sprint::factory()->for($team, 'owner')->create();
    $issue = Issue::factory()->for($project)->create(['sprint_id' => $sprint->id]);

    QuillServer::actingAs($user)->tool(DeleteSprintTool::class, ['sprint_id' => $sprint->id])->assertOk();

    expect($sprint->fresh())->toBeNull();
    expect($issue->fresh()->sprint_id)->toBeNull();
});

test('it starts and completes a sprint', function () {
    [$user, , $team, $project] = sprintToolMember();
    $sprint = Sprint::factory()->for($team, 'owner')->create();
    $unfinished = Issue::factory()->for($project)->create(['sprint_id' => $sprint->id, 'status' => IssueStatus::InProgress]);

    QuillServer::actingAs($user)->tool(StartSprintTool::class, ['sprint_id' => $sprint->id])->assertOk();
    expect($sprint->fresh()->status)->toBe(SprintStatus::Active);

    QuillServer::actingAs($user)->tool(CompleteSprintTool::class, ['sprint_id' => $sprint->id])->assertOk();
    expect($sprint->fresh()->status)->toBe(SprintStatus::Completed);
    expect($unfinished->fresh())->sprint_id->toBeNull()->status->toBe(IssueStatus::Backlog);
});

test('starting a sprint that is not planned is refused', function () {
    [$user, , $team] = sprintToolMember();
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();

    QuillServer::actingAs($user)->tool(StartSprintTool::class, ['sprint_id' => $sprint->id])
        ->assertHasErrors(['Only a planned sprint can be started.']);
});

test('it moves an issue onto a sprint column at a position', function () {
    [$user, , $team, $project] = sprintToolMember('Member');
    $sprint = Sprint::factory()->for($team, 'owner')->create();
    $column = $team->columns()->where('status', IssueStatus::InProgress)->sole();
    $existing = Issue::factory()->for($project)->create(['sprint_id' => $sprint->id]);
    $existing->moveToColumn($column);
    $issue = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(MoveSprintIssueTool::class, ['sprint_id' => $sprint->id, 'column_id' => $column->id, 'issue_id' => $issue->id, 'position' => 0])
        ->assertOk();

    expect($issue->fresh())->sprint_id->toBe($sprint->id)->board_column_id->toBe($column->id)->status->toBe(IssueStatus::InProgress)->position->toBe(0);
    expect($existing->fresh()->position)->toBe(1);
});

test('it will not move an issue onto another board\'s column', function () {
    [$user, $organization, $team, $project] = sprintToolMember();
    $sprint = Sprint::factory()->for($team, 'owner')->create();
    $otherColumn = Team::factory()->for($organization)->create()->columns()->first();
    $issue = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(MoveSprintIssueTool::class, ['sprint_id' => $sprint->id, 'column_id' => $otherColumn->id, 'issue_id' => $issue->id])
        ->assertHasErrors();

    expect($issue->fresh()->sprint_id)->toBeNull();
});
