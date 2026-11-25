<?php

use App\Enums\IssueStatus;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Teams\AddTeamMemberTool;
use App\Mcp\Tools\Teams\CreateBoardColumnTool;
use App\Mcp\Tools\Teams\CreateTeamTool;
use App\Mcp\Tools\Teams\DeleteBoardColumnTool;
use App\Mcp\Tools\Teams\DeleteTeamTool;
use App\Mcp\Tools\Teams\ListTeamsTool;
use App\Mcp\Tools\Teams\RemoveTeamMemberTool;
use App\Mcp\Tools\Teams\ReorderBoardColumnsTool;
use App\Mcp\Tools\Teams\ShowTeamTool;
use App\Mcp\Tools\Teams\SwitchTeamTool;
use App\Mcp\Tools\Teams\UpdateBoardColumnTool;
use App\Mcp\Tools\Teams\UpdateTeamModulesTool;
use App\Mcp\Tools\Teams\UpdateTeamTool;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;

function teamToolMember(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization, Team::factory()->for($organization)->create(['name' => 'Design'])];
}

test('it lists the teams in the organization', function () {
    [$user] = teamToolMember('Member');
    Team::factory()->create(['name' => 'Legal']);

    QuillServer::actingAs($user)->tool(ListTeamsTool::class)
        ->assertOk()
        ->assertSee('Design')
        ->assertDontSee('Legal');
});

test('it shows a team by slug with its members and columns', function () {
    [$user, , $team] = teamToolMember('Member');
    $team->members()->attach(User::factory()->create(['name' => 'Ada Lovelace']));

    QuillServer::actingAs($user)->tool(ShowTeamTool::class, ['team' => $team->slug])
        ->assertOk()
        ->assertSee(['Ada Lovelace', 'In progress']);
});

test('it does not show a team from another organization', function () {
    [$user] = teamToolMember();
    $foreign = Team::factory()->create();

    QuillServer::actingAs($user)->tool(ShowTeamTool::class, ['team' => $foreign->id])->assertHasErrors();
});

test('admins create, rename and delete teams', function () {
    [$user, $organization, $team] = teamToolMember();
    $project = Project::factory()->for($organization)->create();
    $issue = Issue::factory()->for($project)->create(['team_id' => $team->id]);

    QuillServer::actingAs($user)->tool(CreateTeamTool::class, ['name' => 'Platform'])->assertOk()->assertSee('Platform');
    $platform = $organization->teams()->where('name', 'Platform')->sole();

    QuillServer::actingAs($user)->tool(UpdateTeamTool::class, ['team' => $team->id, 'name' => 'Product design'])->assertOk();
    expect($team->fresh()->name)->toBe('Product design');

    QuillServer::actingAs($user)->tool(DeleteTeamTool::class, ['team' => $team->id, 'target_team_id' => $platform->id])->assertOk();
    expect($team->fresh())->toBeNull();
    expect($issue->fresh()->team_id)->toBe($platform->id);
});

test('members cannot create, rename or delete teams', function () {
    [$user, , $team] = teamToolMember('Member');

    QuillServer::actingAs($user)->tool(CreateTeamTool::class, ['name' => 'Platform'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(UpdateTeamTool::class, ['team' => $team->id, 'name' => 'Changed'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeleteTeamTool::class, ['team' => $team->id])->assertHasErrors();

    expect(Team::count())->toBe(1);
    expect($team->fresh()->name)->toBe('Design');
});

test('deleting a team will not move its work to another organization\'s team', function () {
    [$user, , $team] = teamToolMember();
    $foreign = Team::factory()->create();

    QuillServer::actingAs($user)->tool(DeleteTeamTool::class, ['team' => $team->id, 'target_team_id' => $foreign->id])->assertHasErrors();

    expect($team->fresh())->not->toBeNull();
});

test('it switches your current team', function () {
    [$user, $organization] = teamToolMember('Member');
    $other = Team::factory()->for($organization)->create(['name' => 'Support']);

    QuillServer::actingAs($user)->tool(SwitchTeamTool::class, ['team' => $other->slug])->assertOk();

    expect($user->fresh()->current_team_id)->toBe($other->id);
});

test('switching off sprints returns the team\'s sprint work to the backlog', function () {
    [$user, $organization, $team] = teamToolMember();
    $sprint = Sprint::factory()->for($team, 'owner')->create();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create(['sprint_id' => $sprint->id]);

    QuillServer::actingAs($user)->tool(UpdateTeamModulesTool::class, ['team' => $team->id, 'sprints_enabled' => false])->assertOk();

    expect($team->fresh()->moduleSettings()['sprints'])->toBeFalse();
    expect($issue->fresh()->sprint_id)->toBeNull();
});

test('members cannot change team modules', function () {
    [$user, , $team] = teamToolMember('Member');

    QuillServer::actingAs($user)->tool(UpdateTeamModulesTool::class, ['team' => $team->id, 'sprints_enabled' => false])->assertHasErrors();

    expect($team->fresh()->moduleSettings()['sprints'])->toBeTrue();
});

test('admins add and remove team members', function () {
    [$user, $organization, $team] = teamToolMember();
    $colleague = User::factory()->create(['name' => 'Grace Hopper']);
    $organization->addMember($colleague, 'Member');

    QuillServer::actingAs($user)->tool(AddTeamMemberTool::class, ['team' => $team->id, 'user_id' => $colleague->id])->assertOk()->assertSee('Grace Hopper');
    expect($team->members()->whereKey($colleague->id)->exists())->toBeTrue();

    QuillServer::actingAs($user)->tool(RemoveTeamMemberTool::class, ['team' => $team->id, 'user_id' => $colleague->id])->assertOk();
    expect($team->members()->whereKey($colleague->id)->exists())->toBeFalse();
});

test('only organization members can be added to a team', function () {
    [$user, , $team] = teamToolMember();
    $outsider = User::factory()->create();

    QuillServer::actingAs($user)->tool(AddTeamMemberTool::class, ['team' => $team->id, 'user_id' => $outsider->id])
        ->assertHasErrors(['Only organization members can be added to a team.']);
});

test('members cannot add people to a team', function () {
    [$user, $organization, $team] = teamToolMember('Member');
    $colleague = User::factory()->create();
    $organization->addMember($colleague, 'Member');

    QuillServer::actingAs($user)->tool(AddTeamMemberTool::class, ['team' => $team->id, 'user_id' => $colleague->id])->assertHasErrors();

    expect($team->members()->exists())->toBeFalse();
});

test('admins add, change, reorder and remove board columns', function () {
    [$user, $organization, $team] = teamToolMember();

    QuillServer::actingAs($user)->tool(CreateBoardColumnTool::class, ['team' => $team->id, 'name' => 'In review', 'status' => 'in_progress'])->assertOk();
    $review = $team->columns()->where('name', 'In review')->sole();
    expect($review->position)->toBe(3);

    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $issue->moveToColumn($review);

    QuillServer::actingAs($user)->tool(UpdateBoardColumnTool::class, ['team' => $team->id, 'column_id' => $review->id, 'status' => 'done'])->assertOk();
    expect($review->fresh())->name->toBe('In review')->status->toBe(IssueStatus::Done);
    expect($issue->fresh()->status)->toBe(IssueStatus::Done);

    $order = $team->columns()->pluck('id')->reverse()->values()->all();
    QuillServer::actingAs($user)->tool(ReorderBoardColumnsTool::class, ['team' => $team->id, 'column_ids' => $order])->assertOk();
    expect($team->columns()->pluck('id')->all())->toBe($order);

    QuillServer::actingAs($user)->tool(DeleteBoardColumnTool::class, ['team' => $team->id, 'column_id' => $review->id])->assertOk();
    expect($review->fresh())->toBeNull();
    expect($issue->fresh()->board_column_id)->not->toBeNull();
});

test('reordering board columns must name every column', function () {
    [$user, , $team] = teamToolMember();
    $columns = $team->columns()->pluck('id')->all();

    QuillServer::actingAs($user)->tool(ReorderBoardColumnsTool::class, ['team' => $team->id, 'column_ids' => [$columns[1]]])->assertHasErrors();

    expect($team->columns()->pluck('id')->all())->toBe($columns);
});

test('members cannot manage board columns', function () {
    [$user, , $team] = teamToolMember('Member');
    $column = $team->columns()->first();

    QuillServer::actingAs($user)->tool(CreateBoardColumnTool::class, ['team' => $team->id, 'name' => 'QA', 'status' => 'in_progress'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(UpdateBoardColumnTool::class, ['team' => $team->id, 'column_id' => $column->id, 'name' => 'Renamed'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeleteBoardColumnTool::class, ['team' => $team->id, 'column_id' => $column->id])->assertHasErrors();

    expect($team->columns()->count())->toBe(3);
    expect($column->fresh()->name)->not->toBe('Renamed');
});

test('a board keeps at least one column', function () {
    [$user, , $team] = teamToolMember();
    $columns = $team->columns()->get();
    $columns->slice(1)->each->delete();

    QuillServer::actingAs($user)->tool(DeleteBoardColumnTool::class, ['team' => $team->id, 'column_id' => $columns->first()->id])
        ->assertHasErrors(['A board needs at least one column.']);
});
