<?php

use App\Models\Comment;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

test('deleting a feature deletes its comments, replies and mentions', function () {
    $feature = Feature::factory()->create();
    $parent = Comment::factory()->for($feature, 'commentable')->create();
    $reply = Comment::factory()->for($feature, 'commentable')->replyTo($parent)->create();
    $reply->mentions()->sync([User::factory()->create()->id]);

    $feature->delete();

    expect(Comment::whereKey([$parent->id, $reply->id])->count())->toBe(0)
        ->and(DB::table('comment_mentions')->where('comment_id', $reply->id)->count())->toBe(0);
});

test('deleting an issue deletes its comments', function () {
    $issue = Issue::factory()->for(Project::factory())->create();
    $comment = Comment::factory()->for($issue, 'commentable')->create();

    $issue->delete();

    expect(Comment::whereKey($comment->id)->exists())->toBeFalse();
});

test('deleting a comment leaves its commentable alone', function () {
    $feature = Feature::factory()->create();
    $comment = Comment::factory()->for($feature, 'commentable')->create();

    $comment->delete();

    expect($feature->fresh())->not->toBeNull();
});
