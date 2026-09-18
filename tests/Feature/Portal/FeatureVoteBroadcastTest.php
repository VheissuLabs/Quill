<?php

use App\Enums\VoteDirection;
use App\Events\FeatureCommented;
use App\Events\FeatureVoted;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('casting a vote announces it on the project channel', function () {
    Event::fake([FeatureVoted::class]);

    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();

    $feature->castVote(User::factory()->create(), VoteDirection::Up);

    Event::assertDispatched(
        FeatureVoted::class,
        fn (FeatureVoted $event) => $event->projectId === $project->id
            && $event->featureId === $feature->id
    );
});

test('withdrawing a vote announces it too', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();
    $voter = User::factory()->create();
    $feature->castVote($voter, VoteDirection::Up);

    Event::fake([FeatureVoted::class]);

    $feature->clearVoteFrom($voter);

    expect($feature->votes()->where('user_id', $voter->id)->exists())->toBeFalse();

    Event::assertDispatched(
        FeatureVoted::class,
        fn (FeatureVoted $event) => $event->featureId === $feature->id
    );
});

test('the vote announcement rides the public project channel', function () {
    $event = new FeatureVoted(7, 42);

    expect($event->broadcastOn()->name)->toBe('projects.7')
        ->and($event->broadcastAs())->toBe('feature.voted')
        ->and($event->broadcastWith())->toBe(['feature' => 42]);
});

test('commenting on a feature announces it on the project channel', function () {
    Event::fake([FeatureCommented::class]);

    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();

    $feature->comments()->create([
        'body' => 'Please', 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    Event::assertDispatched(
        FeatureCommented::class,
        fn ($event) => $event->projectId === $project->id && $event->featureId === $feature->id
    );
});

test('deleting a comment announces it so the count falls', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();
    $comment = $feature->comments()->create([
        'body' => 'Please', 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    Event::fake([FeatureCommented::class]);

    $comment->delete();

    Event::assertDispatched(FeatureCommented::class);
});

test('deleting a feature takes its comments quietly', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();

    foreach (range(1, 3) as $index) {
        $feature->comments()->create([
            'body' => "Comment {$index}", 'user_id' => User::factory()->create()->id, 'is_internal' => false,
        ]);
    }

    Event::fake([FeatureCommented::class]);

    $feature->delete();

    Event::assertNotDispatched(FeatureCommented::class);
});
