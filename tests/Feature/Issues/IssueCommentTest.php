<?php

use App\Models\Comment;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MentionedInComment;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function issueFor(Organization $organization): Issue
{
    return Issue::factory()->for(Project::factory()->for($organization))->create();
}

test('a member can comment on an issue', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $issue = issueFor($organization);

    $this->actingAs($member)
        ->post(route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]), [
            'body' => 'This still repros on staging.',
        ])
        ->assertRedirect();

    expect($issue->comments()->count())->toBe(1)
        ->and($issue->comments()->first()->body)->toBe('This still repros on staging.');
});

test('the issue page shows its comment thread', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $issue = issueFor($organization);
    $parent = Comment::factory()->for($issue, 'commentable')->create(['body' => 'Top level']);
    Comment::factory()->for($issue, 'commentable')->replyTo($parent)->create(['body' => 'A reply']);

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.body', 'Top level')
            ->has('comments.data.0.replies', 1)
            ->where('comments.data.0.replies.0.body', 'A reply'));
});

test('a non member cannot comment on an issue', function () {
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();
    $issue = issueFor($organization);

    $this->actingAs($outsider)
        ->post(route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]), [
            'body' => 'Let me in',
        ])
        ->assertForbidden();

    expect($issue->comments()->count())->toBe(0);
});

test('a member can delete their own issue comment but not another persons', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $organization->addMember($other, 'Member');
    $issue = issueFor($organization);
    $mine = Comment::factory()->for($issue, 'commentable')->for($member, 'author')->create();
    $theirs = Comment::factory()->for($issue, 'commentable')->for($other, 'author')->create();

    $this->actingAs($member)
        ->delete(route('issues.comments.destroy', ['current_organization' => $organization, 'issue' => $issue, 'comment' => $mine]))
        ->assertRedirect();

    $this->actingAs($member)
        ->delete(route('issues.comments.destroy', ['current_organization' => $organization, 'issue' => $issue, 'comment' => $theirs]))
        ->assertForbidden();

    expect(Comment::whereKey($mine->id)->exists())->toBeFalse()
        ->and(Comment::whereKey($theirs->id)->exists())->toBeTrue();
});

test('mentioning someone on an issue notifies them with a link to the issue', function () {
    Notification::fake();

    $member = User::factory()->create();
    $mentioned = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $organization->addMember($mentioned, 'Member');
    $issue = issueFor($organization);

    $this->actingAs($member)
        ->post(route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]), [
            'body' => 'Can you look at this?',
            'mentions' => [$mentioned->id],
        ])
        ->assertRedirect();

    Notification::assertSentTo($mentioned, MentionedInComment::class, function (MentionedInComment $notification) use ($organization, $issue) {
        return $notification->toArray($notification)['url']
            === route('issues.show', ['current_organization' => $organization, 'issue' => $issue], absolute: false);
    });
});

test('someone outside the organization cannot be mentioned', function () {
    $member = User::factory()->create();
    $stranger = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $issue = issueFor($organization);

    $this->actingAs($member)
        ->post(route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]), [
            'body' => 'Hello',
            'mentions' => [$stranger->id],
        ])
        ->assertSessionHasErrors('mentions.0');
});

test('issue comments are gone when the issues module is switched off', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $organization->capabilitySettings()->create([
        'module' => 'development',
        'capability' => 'issues',
        'enabled' => false,
    ]);
    $issue = issueFor($organization);

    $this->actingAs($member)
        ->post(route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]), [
            'body' => 'Anyone there?',
        ])
        ->assertForbidden();
});
