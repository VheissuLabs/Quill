<?php

use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Organization;
use App\Models\Project;

test('the project page lists its open issues', function () {
    [$organization, $owner] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create(['name' => 'Acme Website']);

    $open = Issue::factory()->inProject($project)->create(['title' => 'Export is broken']);
    $closed = Issue::factory()->inProject($project)->create(['title' => 'Old thing', 'closed_at' => now()]);

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('issues.0.title', 'Export is broken')
            ->where('issues.0.number', $open->number)
            ->has('issues', 1)
            ->where('closedIssueCount', 1),
        );
});

test('a member holding issue:create can file one', function () {
    [$organization, $member] = organizationWith('member');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $type = IssueType::where('organization_id', $organization->id)->first();

    $this->actingAs($member)
        ->post(route('projects.issues.store', $project), [
            'issue_type_id' => $type->id,
            'title' => 'Export is broken',
            'description' => 'Clicking export produces no file.',
        ])
        ->assertRedirect();

    expect($project->issues()->sole()->title)->toBe('Export is broken');
});

test('a contact cannot reach a project issue page', function () {
    [$organization, $owner] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $issue = Issue::factory()->inProject($project)->create();
    $contact = contactFor($client, 'Lucy Alvarez');

    $contact->switchOrganization($organization);

    $this->actingAs($contact->refresh())
        ->get(route('projects.issues.show', [$project, $issue->number]))
        ->assertForbidden();
});

test('an issue in another organization is a not found', function () {
    [, $user] = organizationWith('owner');
    $theirs = Organization::factory()->create(['name' => '92 Labs']);
    $theirClient = Client::factory()->heldBy($theirs)->create();
    $theirProject = Project::factory()->ownedBy($theirClient)->create();
    $issue = Issue::factory()->inProject($theirProject)->create();

    $this->actingAs($user)
        ->get(route('projects.issues.show', [$theirProject, $issue->number]))
        ->assertNotFound();
});

test('closing and reopening an issue', function () {
    [$organization, $owner] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $issue = Issue::factory()->inProject($project)->create();

    $this->actingAs($owner)
        ->post(route('projects.issues.closure.store', [$project, $issue->number]))
        ->assertRedirect();

    expect($issue->fresh()->closed_at)->not->toBeNull();

    $this->actingAs($owner)
        ->delete(route('projects.issues.closure.destroy', [$project, $issue->number]))
        ->assertRedirect();

    expect($issue->fresh()->closed_at)->toBeNull();
});
