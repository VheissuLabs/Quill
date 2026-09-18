<?php

use App\Http\Resources\ActivityResource;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('deleting a comment records what it said against the request', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();
    $author = User::factory()->create(['name' => 'Grace Hopper']);

    $comment = $feature->comments()->create([
        'body' => 'This was said out loud and then taken back',
        'user_id' => $author->id,
        'is_internal' => false,
    ]);

    $comment->delete();

    $entry = Activity::query()
        ->where('subject_type', $feature->getMorphClass())
        ->where('subject_id', $feature->getKey())
        ->where('event', 'comment_deleted')
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull();

    $attributes = $entry->attribute_changes['attributes'];

    expect($attributes['comment'])->toBe('This was said out loud and then taken back')
        ->and($attributes['author'])->toBe('Grace Hopper');
});

test('a deleted comment is readable in full rather than trimmed', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();
    $body = str_repeat('a long comment that runs past eighty characters ', 4);

    $comment = $feature->comments()->create([
        'body' => $body, 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    $comment->delete();

    $entry = Activity::query()->where('event', 'comment_deleted')->latest('id')->first();
    $changes = ActivityResource::make($entry)->resolve()['changes'];

    expect(collect($changes)->firstWhere('field', 'Comment')['to'])->toBe($body);
});

test('deleting the whole request does not log each comment leaving with it', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feature = Feature::factory()->for($project)->create();

    $feature->comments()->create([
        'body' => 'Going with the feature', 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    $feature->delete();

    expect(Activity::query()->where('event', 'comment_deleted')->count())->toBe(0);
});
