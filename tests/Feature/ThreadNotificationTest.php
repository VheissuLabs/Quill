<?php

use App\Models\Feature;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Notifications\MentionedInComment;
use App\Notifications\RepliedInThread;
use Illuminate\Support\Facades\Notification;

function issueThread(): array
{
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $issue = Issue::factory()->for($project)->create();

    $starter = User::factory()->create();
    $replier = User::factory()->create();
    $newcomer = User::factory()->create();

    foreach ([$starter, $replier, $newcomer] as $member) {
        $organization->addMember($member, 'Member');
    }

    $root = $issue->comments()->create(['body' => 'Root', 'user_id' => $starter->id, 'is_internal' => false]);
    $issue->comments()->create(['body' => 'A reply', 'user_id' => $replier->id, 'is_internal' => false, 'parent_id' => $root->id]);

    return [$organization, $issue, $root, $starter, $replier, $newcomer];
}

function postReply(Issue $issue, User $actor, array $payload): void
{
    test()->actingAs($actor)
        ->post(route('issues.comments.store', [
            'current_organization' => $issue->project->organization,
            'issue' => $issue,
        ]), $payload)
        ->assertRedirect();
}

test('replying notifies everyone already in the thread except the replier', function () {
    Notification::fake();

    [, $issue, $root, $starter, $replier, $newcomer] = issueThread();

    postReply($issue, $newcomer, ['body' => 'Joining in', 'parent_id' => $root->id]);

    Notification::assertSentTo($starter, RepliedInThread::class);
    Notification::assertSentTo($replier, RepliedInThread::class);
    Notification::assertNotSentTo($newcomer, RepliedInThread::class);
});

test('a mention in the reply does not also send a thread notification', function () {
    Notification::fake();

    [, $issue, $root, $starter, , $newcomer] = issueThread();

    postReply($issue, $newcomer, ['body' => 'Joining in', 'parent_id' => $root->id, 'mentions' => [$starter->id]]);

    Notification::assertSentTo($starter, MentionedInComment::class);
    Notification::assertNotSentTo($starter, RepliedInThread::class);
});

test('a comment that starts no thread notifies nobody', function () {
    Notification::fake();

    [, $issue, , , , $newcomer] = issueThread();

    postReply($issue, $newcomer, ['body' => 'Fresh thought']);

    Notification::assertNothingSent();
});

test('an internal reply stays away from people outside the organization', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create();
    $feature = Feature::factory()->for($project)->create();

    $outsider = User::factory()->create();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $root = $feature->comments()->create([
        'body' => 'Customer question', 'user_id' => $outsider->id, 'is_internal' => false,
    ]);

    $this->actingAs($member)
        ->post(route('portal.features.comments.store', [
            'organization' => $organization, 'project' => $project, 'feature' => $feature,
        ]), ['body' => 'Internal note', 'parent_id' => $root->id, 'internal' => true])
        ->assertRedirect();

    Notification::assertNotSentTo($outsider, RepliedInThread::class);
});
