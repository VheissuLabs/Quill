<?php

use App\Enums\FeatureStatus;
use App\Enums\OrganizationRole;
use App\Models\Comment;
use App\Models\Feature;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MentionedInComment;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('signed in users can request features on public boards', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('portal.features.store', [$project->organization, $project]), [
            'title' => 'Dark mode',
            'description' => 'Please.',
        ]);

    $feature = Feature::firstWhere('title', 'Dark mode');

    $response->assertRedirect(route('portal.features.show', [$project->organization, $project, $feature]));

    expect($feature)
        ->user_id->toBe($user->id)
        ->status->toBe(FeatureStatus::Open);
});

test('guests cannot request features', function () {
    $project = Project::factory()->public()->create();

    $response = $this->post(route('portal.features.store', [$project->organization, $project]), [
        'title' => 'Dark mode',
    ]);

    $response->assertRedirect(route('login'));
});

test('users outside the organization cannot request features on private boards', function () {
    $outsider = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this
        ->actingAs($outsider)
        ->post(route('portal.features.store', [$project->organization, $project]), [
            'title' => 'Dark mode',
        ]);

    $response->assertForbidden();
});

test('requesting a feature requires a title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('portal.features.store', [$project->organization, $project]), []);

    $response->assertSessionHasErrors('title');
});

test('the feature page shows the discussion and the viewer abilities', function () {
    $author = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->for($author, 'author')->create();
    Comment::factory()->for($feature)->create(['body' => 'Great idea']);

    $response = $this
        ->actingAs($author)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/Feature')
            ->where('feature.id', $feature->id)
            ->where('feature.authorName', $author->name)
            ->has('comments', 1)
            ->where('comments.0.body', 'Great idea')
            ->where('comments.0.canDelete', false)
            ->where('viewer.canUpdate', true)
            ->where('viewer.canUpdateStatus', false)
            ->where('viewer.canDelete', true),
        );
});

test('authors can edit their own features but not other features', function () {
    $author = User::factory()->create();
    $project = Project::factory()->public()->create();
    $ownFeature = Feature::factory()->for($project)->for($author, 'author')->create();
    $otherFeature = Feature::factory()->for($project)->create();

    $this->actingAs($author)
        ->patch(route('portal.features.update', [$project->organization, $project, $ownFeature]), ['title' => 'Edited'])
        ->assertRedirect();

    $this->actingAs($author)
        ->patch(route('portal.features.update', [$project->organization, $project, $otherFeature]), ['title' => 'Edited'])
        ->assertForbidden();

    expect($ownFeature->fresh()->title)->toBe('Edited');
});

test('voting is one vote per user, can switch direction, and can be withdrawn', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();

    $this->actingAs($user)->post(route('portal.features.vote.store', [$project->organization, $project, $feature]))->assertRedirect();
    $this->actingAs($user)->post(route('portal.features.vote.store', [$project->organization, $project, $feature]))->assertRedirect();

    expect($feature->votes()->count())->toBe(1);
    expect($feature->votes()->value('direction'))->toBe(1);

    $this->actingAs($user)->post(route('portal.features.vote.store', [$project->organization, $project, $feature]), ['direction' => 'down'])->assertRedirect();

    expect($feature->votes()->count())->toBe(1);
    expect($feature->votes()->value('direction'))->toBe(-1);

    $this->actingAs($user)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feature.vote', 'down')
            ->where('feature.votesCount', 0)
            ->where('feature.downvotesCount', 1)
            ->where('feature.score', -1),
        );

    $this->actingAs($user)->delete(route('portal.features.vote.destroy', [$project->organization, $project, $feature]))->assertRedirect();

    expect($feature->votes()->count())->toBe(0);
});

test('users outside the organization cannot vote on private boards', function () {
    $outsider = User::factory()->create();
    $project = Project::factory()->create();
    $feature = Feature::factory()->for($project)->create();

    $response = $this
        ->actingAs($outsider)
        ->post(route('portal.features.vote.store', [$project->organization, $project, $feature]));

    $response->assertForbidden();
});

test('admins can change a feature status but members cannot', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();

    $admin = User::factory()->create();
    $project->organization->addMember($admin, OrganizationRole::Admin);

    $member = User::factory()->create();
    $project->organization->addMember($member, OrganizationRole::Member);

    $this->actingAs($member)
        ->patch(route('portal.features.status.update', [$project->organization, $project, $feature]), ['status' => FeatureStatus::Planned->value])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('portal.features.status.update', [$project->organization, $project, $feature]), ['status' => FeatureStatus::Planned->value])
        ->assertRedirect();

    expect($feature->fresh()->status)->toBe(FeatureStatus::Planned);
});

test('admins can delete any feature', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, OrganizationRole::Admin);

    $response = $this
        ->actingAs($admin)
        ->delete(route('portal.features.destroy', [$project->organization, $project, $feature]));

    $response->assertRedirect(route('portal.projects.show', [$project->organization, $project]));

    $this->assertModelMissing($feature);
});

test('signed in users can comment and delete their own comments', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();

    $this->actingAs($user)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), ['body' => 'Yes please'])
        ->assertRedirect();

    $comment = $feature->comments()->firstWhere('body', 'Yes please');

    expect($comment->user_id)->toBe($user->id);

    $this->actingAs($user)
        ->delete(route('portal.features.comments.destroy', [$project->organization, $project, $feature, $comment]))
        ->assertRedirect();

    $this->assertModelMissing($comment);
});

test('comments cannot be deleted by other users', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $comment = Comment::factory()->for($feature)->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('portal.features.comments.destroy', [$project->organization, $project, $feature, $comment]));

    $response->assertForbidden();
});

test('internal comments are only shown to organization members', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    Comment::factory()->for($feature)->create(['body' => 'Public note']);
    Comment::factory()->for($feature)->internal()->create(['body' => 'Internal note']);
    $member = User::factory()->create();
    $project->organization->addMember($member, OrganizationRole::Member);

    $this->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 1)
            ->where('comments.0.body', 'Public note')
            ->where('feature.commentsCount', 1),
        );

    $this->actingAs($member)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.1.isInternal', true)
            ->where('viewer.canCommentInternally', true),
        );
});

test('only organization members can leave internal comments', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $outsider = User::factory()->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, OrganizationRole::Member);

    $this->actingAs($outsider)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), ['body' => 'Sneaky', 'internal' => true])
        ->assertForbidden();

    $this->actingAs($member)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), ['body' => 'Team only', 'internal' => true])
        ->assertRedirect();

    expect($feature->comments()->where('is_internal', true)->where('body', 'Team only')->exists())->toBeTrue();
});

test('comments can be replied to and replies to internal comments stay internal', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, OrganizationRole::Member);
    $public = Comment::factory()->for($feature)->create(['body' => 'Public thread']);
    $internal = Comment::factory()->for($feature)->internal()->create(['body' => 'Internal thread']);

    $this->actingAs($member)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), ['body' => 'Public reply', 'parent_id' => $public->id])
        ->assertRedirect();
    $this->actingAs($member)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), ['body' => 'Hidden reply', 'parent_id' => $internal->id])
        ->assertRedirect();

    expect($public->replies()->pluck('body')->all())->toBe(['Public reply']);
    expect($internal->replies()->firstOrFail()->is_internal)->toBeTrue();

    auth()->logout();

    $this->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 1)
            ->where('comments.0.body', 'Public thread')
            ->has('comments.0.replies', 1)
            ->where('comments.0.replies.0.body', 'Public reply'),
        );
});

test('a reply to a reply attaches to the top-level comment', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $user = User::factory()->create();
    $parent = Comment::factory()->for($feature)->create();
    $reply = Comment::factory()->for($feature)->replyTo($parent)->create();

    $this->actingAs($user)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), ['body' => 'Nested', 'parent_id' => $reply->id])
        ->assertRedirect();

    expect($feature->comments()->firstWhere('body', 'Nested')->parent_id)->toBe($parent->id);
});

test('mentioned people are stored and notified, and only involved people can be mentioned', function () {
    Notification::fake();

    $project = Project::factory()->public()->create();
    $requester = User::factory()->create(['name' => 'Ada Lovelace']);
    $feature = Feature::factory()->for($project)->for($requester, 'author')->create();
    $member = User::factory()->create(['name' => 'Grace Hopper']);
    $project->organization->addMember($member, OrganizationRole::Member);
    $stranger = User::factory()->create();
    $commenter = User::factory()->create();

    $this->actingAs($commenter)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), [
            'body' => '@Ada Lovelace and @Grace Hopper, thoughts?',
            'mentions' => [$requester->id, $member->id],
        ])
        ->assertRedirect();

    $comment = $feature->comments()->firstOrFail();

    expect($comment->mentions->pluck('id')->sort()->values()->all())->toBe(collect([$requester->id, $member->id])->sort()->values()->all());
    Notification::assertSentTo([$requester, $member], MentionedInComment::class);
    Notification::assertNotSentTo($commenter, MentionedInComment::class);

    $this->actingAs($commenter)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), [
            'body' => 'Hey @Someone',
            'mentions' => [$stranger->id],
        ])
        ->assertSessionHasErrors(['mentions.0' => 'You can only mention people involved with this request.']);

    $this->actingAs($commenter)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comments.0.mentions.0.name', 'Ada Lovelace')
            ->has('mentionableUsers', 3),
        );
});
