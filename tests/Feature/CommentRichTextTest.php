<?php

use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function aComment(array $attributes = []): Comment
{
    $project = Project::factory()->for(Organization::factory())->create();
    $feedback = Feedback::factory()->for($project)->create();

    return $feedback->comments()->create([
        'user_id' => User::factory()->create()->id,
        'is_internal' => false,
        ...$attributes,
    ]);
}

test('a comment keeps its markup and a readable copy of itself', function () {
    $comment = aComment(['body' => '<p>The <strong>export</strong> hangs</p>']);

    expect($comment->body)->toContain('<strong>export</strong>')
        ->and($comment->body_text)->toBe('The export hangs');
});

test('a comment cannot smuggle a script past the editor', function () {
    $comment = aComment(['body' => '<p>Hi<script>alert(1)</script></p>']);

    expect($comment->body)->not->toContain('script')
        ->and($comment->body_text)->toBe('Hi');
});

test('the readable copy keeps up when the body is edited', function () {
    $comment = aComment(['body' => '<p>First</p>']);

    $comment->update(['body' => '<p>Second</p>']);

    expect($comment->fresh()->body_text)->toBe('Second');
});

test('a command typed in the editor still runs and leaves the body behind', function () {
    $author = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($author, 'Owner');
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);
    $feedback = Feedback::factory()->for($project)->create();

    $this->actingAs($author)
        ->post(route('portal.feedback.comments.store', [
            'organization' => $organization, 'project' => $project, 'feedback' => $feedback,
        ]), ['body' => '<p>Shipping this one.</p><p>/status completed</p>'])
        ->assertRedirect();

    expect($feedback->fresh()->status->value)->toBe('completed');

    $comment = $feedback->comments()->latest('id')->first();

    expect($comment->body)->toContain('Shipping this one.')
        ->and($comment->body)->not->toContain('/status')
        ->and($comment->body_text)->toBe('Shipping this one.');
});

test('a comment that is only a command stores nothing', function () {
    $author = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($author, 'Owner');
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);
    $feedback = Feedback::factory()->for($project)->create();

    $this->actingAs($author)
        ->post(route('portal.feedback.comments.store', [
            'organization' => $organization, 'project' => $project, 'feedback' => $feedback,
        ]), ['body' => '<p>/status declined</p>'])
        ->assertRedirect();

    expect($feedback->fresh()->status->value)->toBe('declined')
        ->and($feedback->comments()->count())->toBe(0);
});
