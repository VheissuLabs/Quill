<?php

use App\Enums\FeedbackStatus;
use App\Enums\VoteDirection;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MentionedInComment;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('signed in users can request feedback on public boards', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('portal.feedback.store', [$project->organization, $project]), [
            'title' => 'Dark mode',
            'description' => 'Please.',
        ]);

    $feedback = Feedback::firstWhere('title', 'Dark mode');

    $response->assertRedirect(route('portal.feedback.show', [$project->organization, $project, $feedback]));

    expect($feedback)
        ->user_id->toBe($user->id)
        ->status->toBe(FeedbackStatus::Open);

    expect($feedback->votes()->where('user_id', $user->id)->value('direction'))->toBe(1);
});

test('guests cannot request feedback', function () {
    $project = Project::factory()->public()->create();

    $response = $this->post(route('portal.feedback.store', [$project->organization, $project]), [
        'title' => 'Dark mode',
    ]);

    $response->assertRedirect(route('login'));
});

test('users outside the organization cannot request feedback on private boards', function () {
    $outsider = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this
        ->actingAs($outsider)
        ->post(route('portal.feedback.store', [$project->organization, $project]), [
            'title' => 'Dark mode',
        ]);

    $response->assertForbidden();
});

test('requesting a feedback requires a title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('portal.feedback.store', [$project->organization, $project]), []);

    $response->assertSessionHasErrors('title');
});

test('the feedback page shows the discussion and the viewer abilities', function () {
    $author = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->for($author, 'author')->create();
    Comment::factory()->for($feedback, 'commentable')->create(['body' => 'Great idea']);

    $response = $this
        ->actingAs($author)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Feedback')
            ->where('feedback.id', $feedback->id)
            ->where('feedback.authorName', $author->name)
            ->has('comments', 1)
            ->where('comments.0.body', 'Great idea')
            ->where('comments.0.canDelete', false)
            ->where('viewer.canUpdate', true)
            ->where('viewer.canUpdateStatus', false)
            ->where('viewer.canDelete', true),
        );
});

test('authors can edit their own feedback but not other feedback', function () {
    $author = User::factory()->create();
    $project = Project::factory()->public()->create();
    $ownFeature = Feedback::factory()->for($project)->for($author, 'author')->create();
    $otherFeature = Feedback::factory()->for($project)->create();

    $this->actingAs($author)
        ->patch(route('portal.feedback.update', [$project->organization, $project, $ownFeature]), ['title' => 'Edited'])
        ->assertRedirect();

    $this->actingAs($author)
        ->patch(route('portal.feedback.update', [$project->organization, $project, $otherFeature]), ['title' => 'Edited'])
        ->assertForbidden();

    expect($ownFeature->fresh()->title)->toBe('Edited');
});

test('voting is one vote per user, can switch direction, and can be withdrawn', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();

    $this->actingAs($user)->post(route('portal.feedback.vote.store', [$project->organization, $project, $feedback]))->assertRedirect();
    $this->actingAs($user)->post(route('portal.feedback.vote.store', [$project->organization, $project, $feedback]))->assertRedirect();

    expect($feedback->votes()->count())->toBe(1);
    expect($feedback->votes()->value('direction'))->toBe(1);

    $this->actingAs($user)->post(route('portal.feedback.vote.store', [$project->organization, $project, $feedback]), ['direction' => 'down'])->assertRedirect();

    expect($feedback->votes()->count())->toBe(1);
    expect($feedback->votes()->value('direction'))->toBe(-1);

    $this->actingAs($user)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedback.vote', 'down')
            ->where('feedback.votesCount', 0)
            ->where('feedback.downvotesCount', 1)
            ->where('feedback.score', -1),
        );

    $this->actingAs($user)->delete(route('portal.feedback.vote.destroy', [$project->organization, $project, $feedback]))->assertRedirect();

    expect($feedback->votes()->count())->toBe(0);
});

test('completed and declined feedback no longer accept votes', function (FeedbackStatus $status) {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create(['status' => $status]);
    $feedback->castVote($user, VoteDirection::Up);

    $this->actingAs($user)
        ->post(route('portal.feedback.vote.store', [$project->organization, $project, $feedback]), ['direction' => 'down'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('portal.feedback.vote.destroy', [$project->organization, $project, $feedback]))
        ->assertForbidden();

    expect($feedback->votes()->value('direction'))->toBe(1);

    $this->actingAs($user)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedback.acceptsVotes', false)
            ->where('viewer.canVote', false),
        );
})->with([FeedbackStatus::Completed, FeedbackStatus::Declined]);

test('users outside the organization cannot vote on private boards', function () {
    $outsider = User::factory()->create();
    $project = Project::factory()->create();
    $feedback = Feedback::factory()->for($project)->create();

    $response = $this
        ->actingAs($outsider)
        ->post(route('portal.feedback.vote.store', [$project->organization, $project, $feedback]));

    $response->assertForbidden();
});

test('admins can change a feedback status but members cannot', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();

    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');

    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->patch(route('portal.feedback.status.update', [$project->organization, $project, $feedback]), ['status' => FeedbackStatus::Planned->value])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('portal.feedback.status.update', [$project->organization, $project, $feedback]), ['status' => FeedbackStatus::Planned->value])
        ->assertRedirect();

    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Planned);
});

test('admins can delete any feedback', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');

    $response = $this
        ->actingAs($admin)
        ->delete(route('portal.feedback.destroy', [$project->organization, $project, $feedback]));

    $response->assertRedirect(route('portal.projects.show', [$project->organization, $project]));

    $this->assertModelMissing($feedback);
});

test('signed in users can comment and delete their own comments', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();

    $this->actingAs($user)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), ['body' => 'Yes please'])
        ->assertRedirect();

    $comment = $feedback->comments()->firstWhere('body', 'Yes please');

    expect($comment->user_id)->toBe($user->id);

    $this->actingAs($user)
        ->delete(route('portal.feedback.comments.destroy', [$project->organization, $project, $feedback, $comment]))
        ->assertRedirect();

    $this->assertModelMissing($comment);
});

test('comments cannot be deleted by other users', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $comment = Comment::factory()->for($feedback, 'commentable')->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('portal.feedback.comments.destroy', [$project->organization, $project, $feedback, $comment]));

    $response->assertForbidden();
});

test('internal comments are only shown to organization members', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    Comment::factory()->for($feedback, 'commentable')->create(['body' => 'Public note']);
    Comment::factory()->for($feedback, 'commentable')->internal()->create(['body' => 'Internal note']);
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');

    $this->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 1)
            ->where('comments.0.body', 'Public note')
            ->where('feedback.commentsCount', 1),
        );

    $this->actingAs($member)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.1.isInternal', true)
            ->where('viewer.canCommentInternally', true),
        );
});

test('only organization members can leave internal comments', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $outsider = User::factory()->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');

    $this->actingAs($outsider)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), ['body' => 'Sneaky', 'internal' => true])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), ['body' => 'Team only', 'internal' => true])
        ->assertRedirect();

    expect($feedback->comments()->where('is_internal', true)->where('body', 'Team only')->exists())->toBeTrue();
});

test('comments can be replied to and replies to internal comments stay internal', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');
    $public = Comment::factory()->for($feedback, 'commentable')->create(['body' => 'Public thread']);
    $internal = Comment::factory()->for($feedback, 'commentable')->internal()->create(['body' => 'Internal thread']);

    $this->actingAs($member)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), ['body' => 'Public reply', 'parent_id' => $public->id])
        ->assertRedirect();
    $this->actingAs($member)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), ['body' => 'Hidden reply', 'parent_id' => $internal->id])
        ->assertRedirect();

    expect($public->replies()->pluck('body')->all())->toBe(['Public reply']);
    expect($internal->replies()->firstOrFail()->is_internal)->toBeTrue();

    auth()->logout();

    $this->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 1)
            ->where('comments.0.body', 'Public thread')
            ->has('comments.0.replies', 1)
            ->where('comments.0.replies.0.body', 'Public reply'),
        );
});

test('a reply to a reply attaches to the top-level comment', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $user = User::factory()->create();
    $parent = Comment::factory()->for($feedback, 'commentable')->create();
    $reply = Comment::factory()->for($feedback, 'commentable')->replyTo($parent)->create();

    $this->actingAs($user)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), ['body' => 'Nested', 'parent_id' => $reply->id])
        ->assertRedirect();

    expect($feedback->comments()->firstWhere('body', 'Nested')->parent_id)->toBe($parent->id);
});

test('mentioned people are stored and notified, and only involved people can be mentioned', function () {
    Notification::fake();

    $project = Project::factory()->public()->create();
    $requester = User::factory()->create(['name' => 'Ada Lovelace']);
    $feedback = Feedback::factory()->for($project)->for($requester, 'author')->create();
    $member = User::factory()->create(['name' => 'Grace Hopper']);
    $project->organization->addMember($member, 'Member');
    $stranger = User::factory()->create();
    $commenter = User::factory()->create();

    $this->actingAs($commenter)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), [
            'body' => '@Ada Lovelace and @Grace Hopper, thoughts?',
            'mentions' => [$requester->id, $member->id],
        ])
        ->assertRedirect();

    $comment = $feedback->comments()->firstOrFail();

    expect($comment->mentions->pluck('id')->sort()->values()->all())->toBe(collect([$requester->id, $member->id])->sort()->values()->all());
    Notification::assertSentTo([$requester, $member], MentionedInComment::class);
    Notification::assertNotSentTo($commenter, MentionedInComment::class);

    $this->actingAs($commenter)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), [
            'body' => 'Hey @Someone',
            'mentions' => [$stranger->id],
        ])
        ->assertSessionHasErrors(['mentions.0' => 'You can only mention people involved with this request.']);

    $this->actingAs($commenter)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.0.mentions.0.name', 'Ada Lovelace')
            ->has('mentionableUsers', 3),
        );
});
