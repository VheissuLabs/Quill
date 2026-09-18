<?php

use App\Http\Resources\ActivityResource;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('deleting a comment records what it said against the request', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();
    $author = User::factory()->create(['name' => 'Grace Hopper']);

    $comment = $feedback->comments()->create([
        'body' => 'This was said out loud and then taken back',
        'user_id' => $author->id,
        'is_internal' => false,
    ]);

    $comment->delete();

    $entry = Activity::query()
        ->where('subject_type', $feedback->getMorphClass())
        ->where('subject_id', $feedback->getKey())
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
    $feedback = Feedback::factory()->for($project)->create();
    $body = str_repeat('a long comment that runs past eighty characters ', 4);

    $comment = $feedback->comments()->create([
        'body' => $body, 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    $comment->delete();

    $entry = Activity::query()->where('event', 'comment_deleted')->latest('id')->first();
    $changes = ActivityResource::make($entry)->resolve()['changes'];

    expect(collect($changes)->firstWhere('field', 'Comment')['to'])->toBe(trim($body));
});

test('deleting the whole request does not log each comment leaving with it', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();

    $feedback->comments()->create([
        'body' => 'Going with the feedback', 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    $feedback->delete();

    expect(Activity::query()->where('event', 'comment_deleted')->count())->toBe(0);
});

test('deleting the top of a thread writes down the replies that go with it', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();

    $root = $feedback->comments()->create([
        'body' => 'The original question',
        'user_id' => User::factory()->create(['name' => 'Ada Lovelace'])->id,
        'is_internal' => false,
    ]);

    foreach ([['Grace Hopper', 'First answer'], ['Alan Turing', 'Second answer']] as [$name, $body]) {
        $feedback->comments()->create([
            'body' => $body,
            'user_id' => User::factory()->create(['name' => $name])->id,
            'is_internal' => false,
            'parent_id' => $root->id,
        ]);
    }

    $root->delete();

    $entry = Activity::query()->where('event', 'comment_deleted')->latest('id')->first();
    $attributes = $entry->attribute_changes['attributes'];

    expect($attributes['comment'])->toBe('The original question')
        ->and($attributes['replies'])->toContain('Grace Hopper: First answer')
        ->and($attributes['replies'])->toContain('Alan Turing: Second answer');

    expect(Comment::query()->where('commentable_id', $feedback->id)->count())->toBe(0);
});

test('deleting a lone comment records no replies', function () {
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();

    $comment = $feedback->comments()->create([
        'body' => 'On its own', 'user_id' => User::factory()->create()->id, 'is_internal' => false,
    ]);

    $comment->delete();

    $entry = Activity::query()->where('event', 'comment_deleted')->latest('id')->first();

    expect($entry->attribute_changes['attributes'])->not->toHaveKey('replies');
});
