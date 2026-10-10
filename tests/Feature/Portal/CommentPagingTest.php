<?php

use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function feedbackWithComments(int $count): array
{
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);
    $feedback = Feedback::factory()->for($project)->create();
    $author = User::factory()->create();

    foreach (range(1, $count) as $index) {
        $feedback->comments()->create([
            'body' => "Comment {$index}",
            'user_id' => $author->id,
            'is_internal' => false,
        ]);
    }

    return [$organization, $project, $feedback];
}

test('a long thread arrives a page at a time', function () {
    [$organization, $project, $feedback] = feedbackWithComments(20);

    $this->get(route('portal.feedback.show', [
        'organization' => $organization, 'project' => $project, 'feedback' => $feedback,
    ]))->assertInertia(fn (Assert $page) => $page
        ->has('comments.data', 15)
        ->where('comments.data.0.body', 'Comment 1')
    );
});

test('the newest first order is the server saying so', function () {
    [$organization, $project, $feedback] = feedbackWithComments(3);

    $this->get(route('portal.feedback.show', [
        'organization' => $organization, 'project' => $project, 'feedback' => $feedback,
        'comments' => 'newest',
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('commentOrder', 'newest')
        ->where('comments.data.0.body', 'Comment 3')
    );
});

test('the heading counts the whole thread, not the page', function () {
    [$organization, $project, $feedback] = feedbackWithComments(20);

    $this->get(route('portal.feedback.show', [
        'organization' => $organization, 'project' => $project, 'feedback' => $feedback,
    ]))->assertInertia(fn (Assert $page) => $page
        ->where('feedback.commentsCount', 20)
        ->has('comments.data', 15)
    );
});
