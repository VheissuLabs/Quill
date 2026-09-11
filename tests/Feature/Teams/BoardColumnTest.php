<?php

use App\Enums\IssueStatus;
use App\Enums\OrganizationRole;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;

test('new teams start with the default board columns', function () {
    $team = Team::factory()->create();

    expect($team->columns->pluck('name')->all())->toBe(['Todo', 'In progress', 'Done']);
    expect($team->columns->pluck('status')->all())->toBe([IssueStatus::Todo, IssueStatus::InProgress, IssueStatus::Done]);
});

test('admins can add, rename and reorder board columns', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, OrganizationRole::Admin);
    $team = Team::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->post(route('teams.columns.store', ['current_organization' => $organization, 'team' => $team]), [
            'name' => 'In review',
            'status' => IssueStatus::InProgress->value,
        ])
        ->assertRedirect();

    $review = $team->columns()->where('name', 'In review')->firstOrFail();

    expect($review->position)->toBe(3);

    $this->actingAs($admin)
        ->patch(route('teams.columns.update', ['current_organization' => $organization, 'team' => $team, 'column' => $review]), [
            'name' => 'QA',
            'status' => IssueStatus::InProgress->value,
        ])
        ->assertRedirect();

    expect($review->fresh()->name)->toBe('QA');

    $ids = $team->columns()->pluck('id')->all();

    $this->actingAs($admin)
        ->put(route('teams.columns.order.update', ['current_organization' => $organization, 'team' => $team]), [
            'column_ids' => [$ids[0], $ids[1], $ids[3], $ids[2]],
        ])
        ->assertRedirect();

    expect($team->columns()->pluck('name')->all())->toBe(['Todo', 'In progress', 'QA', 'Done']);
});

test('members cannot change board columns', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, OrganizationRole::Member);
    $team = Team::factory()->for($organization)->create();

    $this->actingAs($member)
        ->post(route('teams.columns.store', ['current_organization' => $organization, 'team' => $team]), [
            'name' => 'Nope',
            'status' => IssueStatus::Todo->value,
        ])
        ->assertForbidden();
});

test('changing a column status moves its issues to that status', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, OrganizationRole::Admin);
    $team = Team::factory()->for($organization)->create();
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team)->active()->create();
    $column = $team->columns()->where('status', IssueStatus::InProgress)->firstOrFail();
    $issue = Issue::factory()->for($project)->for($sprint)->create();
    $issue->moveToColumn($column);

    $this->actingAs($admin)
        ->patch(route('teams.columns.update', ['current_organization' => $organization, 'team' => $team, 'column' => $column]), [
            'name' => 'Shipped',
            'status' => IssueStatus::Done->value,
        ])
        ->assertRedirect();

    expect($issue->fresh())
        ->status->toBe(IssueStatus::Done)
        ->completed_at->not->toBeNull();
});

test('deleting a column moves its issues to the previous column and the last column cannot be deleted', function () {
    $admin = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($admin, OrganizationRole::Admin);
    $team = Team::factory()->for($organization)->create();
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team)->active()->create();
    [$todo, $inProgress, $done] = $team->columns()->get();
    $issue = Issue::factory()->for($project)->for($sprint)->create();
    $issue->moveToColumn($inProgress);

    $this->actingAs($admin)
        ->delete(route('teams.columns.destroy', ['current_organization' => $organization, 'team' => $team, 'column' => $inProgress]))
        ->assertRedirect();

    expect($issue->fresh())
        ->board_column_id->toBe($todo->id)
        ->status->toBe(IssueStatus::Todo);

    $this->actingAs($admin)
        ->delete(route('teams.columns.destroy', ['current_organization' => $organization, 'team' => $team, 'column' => $todo]))
        ->assertRedirect();

    $this->actingAs($admin)
        ->delete(route('teams.columns.destroy', ['current_organization' => $organization, 'team' => $team, 'column' => $done]))
        ->assertStatus(409);
});
