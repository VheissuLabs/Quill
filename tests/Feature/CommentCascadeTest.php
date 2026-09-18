<?php

use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

test('deleting a feedback deletes its comments, replies and mentions', function () {
    $feedback = Feedback::factory()->create();
    $parent = Comment::factory()->for($feedback, 'commentable')->create();
    $reply = Comment::factory()->for($feedback, 'commentable')->replyTo($parent)->create();
    $reply->mentions()->sync([User::factory()->create()->id]);

    $feedback->delete();

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
    $feedback = Feedback::factory()->create();
    $comment = Comment::factory()->for($feedback, 'commentable')->create();

    $comment->delete();

    expect($feedback->fresh())->not->toBeNull();
});
