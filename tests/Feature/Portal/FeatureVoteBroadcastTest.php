<?php

use App\Enums\VoteDirection;
use App\Events\FeedbackCommented;
use App\Events\FeedbackVoted;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('casting a vote announces it on the project channel', function () {
    Event::fake([FeedbackVoted::class]);

    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();

    $feedback->castVote(User::factory()->create(), VoteDirection::Up);

    Event::assertDispatched(
        FeedbackVoted::class,
        fn (FeedbackVoted $event) => $event->projectId === $project->id
            && $event->feedbackId === $feedback->id
    );
});

test('withdrawing a vote announces it too', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();
    $voter = User::factory()->create();
    $feedback->castVote($voter, VoteDirection::Up);

    Event::fake([FeedbackVoted::class]);

    $feedback->clearVoteFrom($voter);

    expect($feedback->votes()->where('user_id', $voter->id)->exists())->toBeFalse();

    Event::assertDispatched(
        FeedbackVoted::class,
        fn (FeedbackVoted $event) => $event->feedbackId === $feedback->id
    );
});

test('the vote announcement rides the public project channel', function () {
    $event = new FeedbackVoted(7, 42);

    expect($event->broadcastOn()->name)->toBe('projects.7')
        ->and($event->broadcastAs())->toBe('feedback.voted')
        ->and($event->broadcastWith())->toBe(['feedback' => 42]);
});

test('commenting on a feedback announces it on the project channel', function () {
    Event::fake([FeedbackCommented::class]);

    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();

    $feedback->comments()->create([
        'body' => 'Please', 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    Event::assertDispatched(
        FeedbackCommented::class,
        fn ($event) => $event->projectId === $project->id && $event->feedbackId === $feedback->id
    );
});

test('deleting a comment announces it so the count falls', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();
    $comment = $feedback->comments()->create([
        'body' => 'Please', 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    Event::fake([FeedbackCommented::class]);

    $comment->delete();

    Event::assertDispatched(FeedbackCommented::class);
});

test('deleting a feedback takes its comments quietly', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();

    foreach (range(1, 3) as $index) {
        $feedback->comments()->create([
            'body' => "Comment {$index}", 'user_id' => User::factory()->create()->id, 'is_internal' => false,
        ]);
    }

    Event::fake([FeedbackCommented::class]);

    $feedback->delete();

    Event::assertNotDispatched(FeedbackCommented::class);
});

test('a project board refreshes its feedback when any feedback changes, gets a vote or a comment', function () {
    $project = Project::factory()->public()->create();

    $liveProps = $this->get(route('portal.projects.show', [$project->organization, $project]))
        ->viewData('page')['liveProps'];

    expect($liveProps['feedback']['listeners'])->toBe([[
        'channel' => ['name' => "projects.{$project->id}", 'type' => 'public'],
        'events' => ['feedback.changed', 'feedback.voted', 'feedback.commented'],
    ]]);
});
