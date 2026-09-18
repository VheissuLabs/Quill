<?php

use App\Enums\FeedbackType;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function portalArgs(Organization $organization, Project $project): array
{
    return ['organization' => $organization, 'project' => $project];
}

test('feedback is submitted as a bug report or a feature request', function () {
    $author = User::factory()->create();
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);

    $this->actingAs($author)
        ->post(route('portal.feedback.store', portalArgs($organization, $project)), [
            'title' => 'Export crashes on large boards',
            'description' => 'It hangs and then reloads',
            'type' => FeedbackType::Bug->value,
        ])
        ->assertRedirect();

    expect(Feedback::query()->latest('id')->first())
        ->type->toBe(FeedbackType::Bug);
});

test('feedback defaults to a feature request when no type is sent', function () {
    $author = User::factory()->create();
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);

    $this->actingAs($author)
        ->post(route('portal.feedback.store', portalArgs($organization, $project)), [
            'title' => 'Dark mode please',
        ])
        ->assertRedirect();

    expect(Feedback::query()->latest('id')->first())
        ->type->toBe(FeedbackType::Feature);
});

test('an unknown type is refused', function () {
    $author = User::factory()->create();
    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);

    $this->actingAs($author)
        ->post(route('portal.feedback.store', portalArgs($organization, $project)), [
            'title' => 'Something',
            'type' => 'complaint',
        ])
        ->assertSessionHasErrors('type');
});

test('the board carries the type of each item and can filter by it', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);

    Feedback::factory()->for($project)->create(['title' => 'A bug', 'type' => FeedbackType::Bug]);
    Feedback::factory()->for($project)->create(['title' => 'An idea', 'type' => FeedbackType::Feature]);

    $this->actingAs($member)
        ->get(route('portal.projects.show', portalArgs($organization, $project)))
        ->assertInertia(fn (Assert $page) => $page
            ->has('feedback', 2)
            ->where('types', fn ($types) => collect($types)->pluck('value')->all() === ['feature', 'bug'])
        );

    $this->actingAs($member)
        ->get(route('portal.projects.show', [...portalArgs($organization, $project), 'type' => 'bug']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('feedback', 1)
            ->where('feedback.0.title', 'A bug')
            ->where('feedback.0.type', 'bug')
        );
});

test('a bug offers one vote with no opposite and a feature offers both', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);

    $bug = Feedback::factory()->for($project)->bug()->create();
    $idea = Feedback::factory()->for($project)->create();

    $this->actingAs($member)
        ->get(route('portal.feedback.show', [...portalArgs($organization, $project), 'feedback' => $bug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedback.acceptsOpposingVotes', false)
            ->where('feedback.voteLabel', "I've experienced this")
            ->where('feedback.typeLabel', 'Bug report')
        );

    $this->actingAs($member)
        ->get(route('portal.feedback.show', [...portalArgs($organization, $project), 'feedback' => $idea]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedback.acceptsOpposingVotes', true)
            ->where('feedback.voteLabel', 'For')
        );
});
