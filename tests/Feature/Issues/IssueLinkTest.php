<?php

use App\Enums\IssueLinkType;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function linkSetup(): array
{
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();

    return [$member, $organization, $project];
}

function runCommand(User $user, Organization $organization, Issue $issue, string $body)
{
    return test()->actingAs($user)->post(
        route('issues.comments.store', ['current_organization' => $organization, 'issue' => $issue]),
        ['body' => $body],
    );
}

test('an issue can be linked with each relationship type', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);

    foreach (['parent', 'blocks', 'relates', 'duplicates', 'clones'] as $command) {
        runCommand($member, $organization, $issue, "/{$command} {$other->id}")->assertRedirect();
    }

    expect($issue->links()->pluck('type')->map(fn ($type) => $type->value)->sort()->values()->all())
        ->toBe(['blocks', 'clones', 'duplicates', 'parent', 'relates']);
});

test('a link reads from both sides with the right wording', function () {
    [$member, $organization, $project] = linkSetup();
    $blocker = Issue::factory()->for($project)->create(['number' => 1]);
    $blocked = Issue::factory()->for($project)->create(['number' => 2]);

    runCommand($member, $organization, $blocker, "/blocks {$blocked->id}");

    expect($blocker->fresh()->relatedIssues()->first()['label'])->toBe('Blocks')
        ->and($blocked->fresh()->relatedIssues()->first()['label'])->toBe('Blocked by');
});

test('relates reads the same from both sides', function () {
    [$member, $organization, $project] = linkSetup();
    $one = Issue::factory()->for($project)->create(['number' => 1]);
    $two = Issue::factory()->for($project)->create(['number' => 2]);

    runCommand($member, $organization, $one, "/relates {$two->id}");

    expect($one->fresh()->relatedIssues()->first()['label'])->toBe('Relates to')
        ->and($two->fresh()->relatedIssues()->first()['label'])->toBe('Relates to');
});

test('an issue cannot be linked to itself', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);

    runCommand($member, $organization, $issue, "/blocks {$issue->id}")->assertSessionHasErrors('body');

    expect($issue->links()->count())->toBe(0);
});

test('an issue in another organization cannot be linked', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $foreign = Issue::factory()->for(Project::factory())->create(['number' => 1]);

    runCommand($member, $organization, $issue, "/blocks {$foreign->id}")->assertSessionHasErrors('body');

    expect($issue->links()->count())->toBe(0);
});

test('issues in another project of the same organization can be linked', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $sibling = Issue::factory()->for(Project::factory()->for($organization))->create(['number' => 1]);

    runCommand($member, $organization, $issue, "/relates {$sibling->id}")->assertRedirect();

    expect($issue->links()->count())->toBe(1);
});

test('an unknown issue id is refused', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);

    runCommand($member, $organization, $issue, '/blocks 999999')->assertSessionHasErrors('body');
});

test('linking the same pair twice does not duplicate the row', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);

    runCommand($member, $organization, $issue, "/blocks {$other->id}");
    runCommand($member, $organization, $issue, "/blocks {$other->id}");

    expect($issue->links()->count())->toBe(1);
});

test('unlink removes the link from either direction', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);

    runCommand($member, $organization, $issue, "/blocks {$other->id}");
    runCommand($member, $organization, $other, "/unlink {$issue->id}");

    expect(IssueLink::count())->toBe(0);
});

test('deleting an issue takes its links with it', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);
    $issue->linkTo($other, IssueLinkType::Blocks);

    $other->delete();

    expect(IssueLink::count())->toBe(0);
});

test('the issue page lists its links', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2, 'title' => 'The blocker']);
    $other->linkTo($issue, IssueLinkType::Blocks);

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('links', 1)
            ->where('links.0.label', 'Blocked by')
            ->where('links.0.issue.title', 'The blocker'));
});

test('a link can be removed from the issue page', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);
    $link = $issue->linkTo($other, IssueLinkType::Relates);

    $this->actingAs($member)
        ->delete(route('issues.links.destroy', [
            'current_organization' => $organization,
            'issue' => $issue,
            'link' => $link,
        ]))
        ->assertRedirect();

    expect(IssueLink::count())->toBe(0);
});

test('issue lookup finds issues by title for the suggestion list', function () {
    [$member, $organization, $project] = linkSetup();
    $current = Issue::factory()->for($project)->create(['number' => 1, 'title' => 'Orbit drifts']);
    Issue::factory()->for($project)->create(['number' => 2, 'title' => 'Orbit wobbles']);
    Issue::factory()->for($project)->create(['number' => 3, 'title' => 'Unrelated thing']);

    $response = $this->actingAs($member)->getJson(route('issues.lookup', [
        'current_organization' => $organization,
        'q' => 'orbit',
        'except' => $current->id,
    ]));

    $response->assertOk();

    expect(collect($response->json())->pluck('title')->all())->toBe(['Orbit wobbles'])
        ->and($response->json('0.id'))->toBeInt()
        ->and($response->json('0.reference'))->toContain('#2');
});

test('issue lookup never returns issues the viewer cannot see', function () {
    [$member, $organization] = linkSetup();
    $hidden = Project::factory()->for($organization)->create(['visibility' => 'private']);
    $hidden->teams()->attach(Team::factory()->for($organization)->create());
    Issue::factory()->for($hidden)->create(['number' => 1, 'title' => 'Secret orbit']);

    $response = $this->actingAs($member)->getJson(route('issues.lookup', [
        'current_organization' => $organization,
        'q' => 'orbit',
    ]));

    expect($response->json())->toBeEmpty();
});

test('issue lookup ignores a term that is too short', function () {
    [$member, $organization, $project] = linkSetup();
    Issue::factory()->for($project)->create(['number' => 1, 'title' => 'Orbit']);

    $response = $this->actingAs($member)->getJson(route('issues.lookup', [
        'current_organization' => $organization,
        'q' => 'o',
    ]));

    expect($response->json())->toBeEmpty();
});

test('slash child makes this issue the child of another', function () {
    [$member, $organization, $project] = linkSetup();
    $child = Issue::factory()->for($project)->create(['number' => 1]);
    $parent = Issue::factory()->for($project)->create(['number' => 2]);

    runCommand($member, $organization, $child, "/child {$parent->id}")->assertRedirect();

    expect($child->fresh()->relatedIssues()->first()['label'])->toBe('Child of')
        ->and($parent->fresh()->relatedIssues()->first()['label'])->toBe('Parent of')
        ->and(IssueLink::first())
        ->issue_id->toBe($parent->id)
        ->related_issue_id->toBe($child->id);
});

test('slash child and slash parent are the same link from either end', function () {
    [$member, $organization, $project] = linkSetup();
    $child = Issue::factory()->for($project)->create(['number' => 1]);
    $parent = Issue::factory()->for($project)->create(['number' => 2]);

    runCommand($member, $organization, $parent, "/parent {$child->id}");
    runCommand($member, $organization, $child, "/child {$parent->id}");

    expect(IssueLink::count())->toBe(1);
});

test('a relationship can be flipped from child of to parent of', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);
    $other->linkTo($issue, IssueLinkType::Parent);

    expect($issue->relatedIssues()->first()['label'])->toBe('Child of');

    $link = IssueLink::first();

    $this->actingAs($member)
        ->patch(route('issues.links.update', [
            'current_organization' => $organization,
            'issue' => $issue,
            'link' => $link,
        ]), ['type' => 'parent', 'inverse' => false])
        ->assertRedirect();

    expect(IssueLink::count())->toBe(1)
        ->and($issue->fresh()->relatedIssues()->first()['label'])->toBe('Parent of')
        ->and($other->fresh()->relatedIssues()->first()['label'])->toBe('Child of');
});

test('a relationship can be changed to a different type entirely', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);
    $link = $issue->linkTo($other, IssueLinkType::Relates);

    $this->actingAs($member)
        ->patch(route('issues.links.update', [
            'current_organization' => $organization,
            'issue' => $issue,
            'link' => $link,
        ]), ['type' => 'blocks', 'inverse' => true]);

    expect(IssueLink::count())->toBe(1)
        ->and($issue->fresh()->relatedIssues()->first()['label'])->toBe('Blocked by')
        ->and($other->fresh()->relatedIssues()->first()['label'])->toBe('Blocks');
});

test('a relationship can be fixed from either end', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);
    $other = Issue::factory()->for($project)->create(['number' => 2]);
    $link = $issue->linkTo($other, IssueLinkType::Blocks);

    $this->actingAs($member)
        ->patch(route('issues.links.update', [
            'current_organization' => $organization,
            'issue' => $other,
            'link' => $link,
        ]), ['type' => 'blocks', 'inverse' => false])
        ->assertRedirect();

    expect($other->fresh()->relatedIssues()->first()['label'])->toBe('Blocks')
        ->and($issue->fresh()->relatedIssues()->first()['label'])->toBe('Blocked by');
});

test('the issue page offers every relationship reading', function () {
    [$member, $organization, $project] = linkSetup();
    $issue = Issue::factory()->for($project)->create(['number' => 1]);

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn (Assert $page) => $page->has('linkTypes', 9));
});

test('a link belonging to other issues cannot be touched through this one', function () {
    [$member, $organization, $project] = linkSetup();
    $mine = Issue::factory()->for($project)->create(['number' => 1]);
    $a = Issue::factory()->for($project)->create(['number' => 2]);
    $b = Issue::factory()->for($project)->create(['number' => 3]);
    $link = $a->linkTo($b, IssueLinkType::Blocks);

    $this->actingAs($member)
        ->patch(route('issues.links.update', [
            'current_organization' => $organization,
            'issue' => $mine,
            'link' => $link,
        ]), ['type' => 'relates', 'inverse' => false])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('issues.links.destroy', [
            'current_organization' => $organization,
            'issue' => $mine,
            'link' => $link,
        ]))
        ->assertForbidden();

    expect(IssueLink::count())->toBe(1);
});
