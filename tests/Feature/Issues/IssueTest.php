<?php

use App\Models\Client;
use App\Models\Issue;
use App\Models\IssueType;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

function issueFor(Organization $organization, Project $project, ?Client $client = null, ?User $reporter = null): Issue
{
    return Issue::create([
        'organization_id' => $organization->id,
        'project_id' => $project->id,
        'client_id' => $client?->id,
        'issue_type_id' => IssueType::where('organization_id', $organization->id)->first()->id,
        'reported_by' => ($reporter ?? User::factory()->create())->id,
        'title' => 'The export button does nothing',
        'description' => 'Clicking export on the report page produces no file.',
    ]);
}

test('issue numbers are sequential within a project and independent across projects', function () {
    [$organization] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $first = Project::factory()->ownedBy($client)->create();
    $second = Project::factory()->ownedBy($client)->create();

    expect(issueFor($organization, $first, $client)->number)->toBe(1);
    expect(issueFor($organization, $first, $client)->number)->toBe(2);
    expect(issueFor($organization, $second, $client)->number)->toBe(1);
});

test('an issue is open until it is closed', function () {
    [$organization] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $issue = issueFor($organization, Project::factory()->ownedBy($client)->create(), $client);

    expect(Issue::open()->pluck('id'))->toContain($issue->id);

    $issue->close();

    expect($issue->fresh()->closed_at)->not->toBeNull();
    expect(Issue::open()->pluck('id'))->not->toContain($issue->id);
    expect(Issue::closed()->pluck('id'))->toContain($issue->id);

    $issue->reopen();

    expect($issue->fresh()->closed_at)->toBeNull();
});

test('staff may file against a team-owned project with no client', function () {
    [$organization, $owner] = organizationWith('owner');
    $team = Team::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($team)->create();

    $issue = issueFor($organization, $project, null, $owner);

    expect($issue->client_id)->toBeNull();
})->note('Internal work is for nobody, which is why client_id is nullable.');

test('an issue reported by a contact must carry that contact client', function () {
    [$organization] = organizationWith('owner');
    $client = Client::factory()->heldBy($organization)->create();
    $project = Project::factory()->ownedBy($client)->create();
    $contact = contactFor($client, 'Lucy Alvarez');

    expect(fn () => issueFor($organization, $project, null, $contact))
        ->toThrow(RuntimeException::class);
});
