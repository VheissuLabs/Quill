<?php

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function commandOn(Issue $issue, Organization $organization, User $user, string $body): TestResponse
{
    return test()->actingAs($user)->post(
        route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]),
        ['body' => $body],
    );
}

function issueAndMember(): array
{
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();

    return [$issue, $organization, $member];
}

test('slash status changes the issue status and leaves no comment behind', function () {
    [$issue, $organization, $member] = issueAndMember();

    commandOn($issue, $organization, $member, '/status done')->assertRedirect();

    expect($issue->fresh()->status)->toBe(IssueStatus::Done)
        ->and($issue->comments()->count())->toBe(0);
});

test('slash assign accepts a name, me and none', function () {
    [$issue, $organization, $member] = issueAndMember();
    $other = User::factory()->create(['name' => 'Wilhelmina Fox']);
    $organization->addMember($other, 'Member');

    commandOn($issue, $organization, $member, '/assign Wilhelmina');
    expect($issue->fresh()->assignee_id)->toBe($other->id);

    commandOn($issue, $organization, $member, '/assign me');
    expect($issue->fresh()->assignee_id)->toBe($member->id);

    commandOn($issue, $organization, $member, '/assign none');
    expect($issue->fresh()->assignee_id)->toBeNull();
});

test('an ambiguous assignee name is rejected', function () {
    [$issue, $organization, $member] = issueAndMember();
    $organization->addMember(User::factory()->create(['name' => 'Sam Carter']), 'Member');
    $organization->addMember(User::factory()->create(['name' => 'Samantha Diaz']), 'Member');

    commandOn($issue, $organization, $member, '/assign Sam')->assertSessionHasErrors('body');

    expect($issue->fresh()->assignee_id)->toBeNull();
});

test('slash sprint plans the issue and none returns it to the backlog', function () {
    [$issue, $organization, $member] = issueAndMember();
    $sprint = Sprint::factory()->for(Team::factory()->for($organization), 'owner')->create(['name' => 'Sprint 7']);

    commandOn($issue, $organization, $member, '/sprint Sprint 7');
    expect($issue->fresh()->sprint_id)->toBe($sprint->id);

    commandOn($issue, $organization, $member, '/sprint none');
    expect($issue->fresh()->sprint_id)->toBeNull();
});

test('slash points and slash priority set their fields', function () {
    [$issue, $organization, $member] = issueAndMember();

    commandOn($issue, $organization, $member, '/points 8');
    expect($issue->fresh()->points)->toBe(8);

    commandOn($issue, $organization, $member, '/priority urgent');
    expect($issue->fresh()->priority)->toBe(IssuePriority::Urgent);

    commandOn($issue, $organization, $member, '/points none');
    expect($issue->fresh()->points)->toBeNull();
});

test('an unknown command is rejected and nothing is written', function () {
    [$issue, $organization, $member] = issueAndMember();

    commandOn($issue, $organization, $member, '/convert')->assertSessionHasErrors('body');

    expect($issue->comments()->count())->toBe(0);
});

test('a command line runs while the rest of the comment is still posted', function () {
    [$issue, $organization, $member] = issueAndMember();

    commandOn($issue, $organization, $member, "/points 5\nSized it during grooming.");

    expect($issue->fresh()->points)->toBe(5)
        ->and($issue->comments()->first()->body)->toBe('Sized it during grooming.');
});

test('a non member cannot drive an issue through commands', function () {
    [$issue, $organization] = issueAndMember();
    $outsider = User::factory()->create();

    commandOn($issue, $organization, $outsider, '/status done')->assertForbidden();

    expect($issue->fresh()->status)->not->toBe(IssueStatus::Done);
});
