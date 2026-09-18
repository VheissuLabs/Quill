<?php

use App\Events\IssuesChanged;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('changing an issue announces it to its organization', function () {
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $issue = Issue::factory()->for($project)->create();

    Event::fake([IssuesChanged::class]);

    $issue->update(['title' => 'Renamed']);

    Event::assertDispatched(
        IssuesChanged::class,
        fn (IssuesChanged $event) => $event->organizationId === $organization->id
            && $event->issueIds === [$issue->id]
    );
});

test('the announcement rides the organization private channel', function () {
    $event = new IssuesChanged(3, [7, 8]);

    expect($event->broadcastOn()->name)->toBe('private-organizations.3')
        ->and($event->broadcastAs())->toBe('issues.changed')
        ->and($event->broadcastWith())->toBe(['issues' => [7, 8]]);
});

test('reordering a list announces once for every issue it moved', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    $issues = collect(range(1, 3))->map(
        fn (int $number) => Issue::factory()->for($project)->create(['number' => $number])
    );

    Event::fake([IssuesChanged::class]);

    $this->actingAs($member)
        ->put(route('issues.order.update', ['current_organization' => $organization]), [
            'issue_ids' => $issues->pluck('id')->reverse()->values()->all(),
        ])
        ->assertRedirect();

    Event::assertDispatchedTimes(IssuesChanged::class, 1);
    Event::assertDispatched(
        IssuesChanged::class,
        fn (IssuesChanged $event) => count($event->issueIds) === 3
    );

    expect($issues->last()->fresh()->position)->toBe(0);
});

test('completing a sprint announces the issues it sent back', function () {
    $organization = Organization::factory()->create();
    $team = Team::factory()->for($organization)->create();
    $project = Project::factory()->for($organization)->create();
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create();
    $issue = Issue::factory()->for($project)->create();
    $issue->planInto($sprint);

    Event::fake([IssuesChanged::class]);

    $sprint->complete();

    Event::assertDispatched(
        IssuesChanged::class,
        fn (IssuesChanged $event) => in_array($issue->id, $event->issueIds, true)
    );
});
