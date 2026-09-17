<?php

use App\Enums\FeatureStatus;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Http\Resources\ActivityResource;
use App\Models\Comment;
use App\Models\EmailAddress;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

test('feature status changes are logged with who made them and shown to members', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create(['status' => FeatureStatus::Open]);
    $admin = User::factory()->create(['name' => 'Karl Murray']);
    $project->organization->addMember($admin, 'Admin');

    $this->actingAs($admin)
        ->patch(route('portal.features.status.update', [$project->organization, $project, $feature]), ['status' => FeatureStatus::Planned->value])
        ->assertRedirect();

    $activity = $feature->activitiesAsSubject()->latest('id')->first();

    expect($activity)
        ->event->toBe('updated')
        ->log_name->toBe('features')
        ->causer_id->toBe($admin->id);
    expect($activity->attribute_changes['old']['status'])->toBe('open');
    expect($activity->attribute_changes['attributes']['status'])->toBe('planned');

    $this->actingAs($admin)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.0.causerName', 'Karl Murray')
            ->where('activity.0.changes.0.field', 'Status')
            ->where('activity.0.changes.0.from', 'Open')
            ->where('activity.0.changes.0.to', 'Planned'),
        );

    auth()->logout();

    $this->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page->where('activity', []));
});

test('planning an issue logs the sprint and status with related names resolved', function () {
    $member = User::factory()->create(['name' => 'Grace Hopper']);
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Admin');
    $project = Project::factory()->for($organization)->create();
    $team = Team::factory()->for($organization)->create(['name' => 'Development']);
    $sprint = Sprint::factory()->for($team, 'owner')->active()->create(['name' => 'Sprint 4']);
    $issue = Issue::factory()->for($project)->create();

    $this->actingAs($member)
        ->patch(route('issues.update', ['current_organization' => $organization, 'issue' => $issue]), ['sprint_id' => $sprint->id])
        ->assertRedirect();

    expect($issue->fresh()->status)->toBe(IssueStatus::Todo);

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.0.causerName', 'Grace Hopper')
            ->where('activity', fn ($entries) => collect($entries)
                ->flatMap(fn ($entry) => $entry['changes'])
                ->contains(fn ($change) => $change['field'] === 'Sprint' && $change['to'] === 'Sprint 4')),
        );
});

test('activity is only shown to people with the view activity permission', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');
    $feature->update(['status' => FeatureStatus::Planned]);

    $this->actingAs($member)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('viewer.canViewActivity', false)
            ->where('activity', []),
        );
});

test('the issues index lists recent activity for the current team with the issue referenced', function () {
    $admin = User::factory()->create(['name' => 'Ada Lovelace']);
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $project = Project::factory()->for($organization)->create(['name' => 'Quill']);
    $development = Team::factory()->for($organization)->create(['name' => 'Development']);
    $marketing = Team::factory()->for($organization)->create(['name' => 'Marketing']);
    $admin->switchOrganization($organization);
    $admin->switchTeam($development);
    $devIssue = Issue::factory()->for($project)->for($development)->create(['title' => 'Dev work']);
    $untriaged = Issue::factory()->for($project)->create(['title' => 'Untriaged']);
    $campaign = Issue::factory()->for($project)->for($marketing)->create(['title' => 'Campaign']);

    $this->actingAs($admin);
    $devIssue->update(['priority' => IssuePriority::High]);
    $untriaged->update(['priority' => IssuePriority::Low]);
    $campaign->update(['priority' => IssuePriority::Urgent]);

    $this->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('issues/Index')
            ->where('permissions.canViewActivity', true)
            ->where('activity', fn ($entries) => collect($entries)->pluck('issue.title')->all() === ['Untriaged', 'Dev work', 'Untriaged', 'Dev work'])
            ->where('activity.0.event', 'updated')
            ->where('activity.0.causerName', 'Ada Lovelace')
            ->where('activity.0.issue.id', $untriaged->id)
            ->where('activity.0.issue.reference', "Quill #{$untriaged->number}")
            ->where('activity.0.changes.0.field', 'Priority')
            ->where('activity.0.changes.0.to', 'Low'),
        );
});

test('the issues index sends no activity to members without the view activity permission', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');
    $project = Project::factory()->for($organization)->create();
    Issue::factory()->for($project)->create()->update(['priority' => IssuePriority::High]);

    $this->actingAs($member)
        ->get(route('issues.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canViewActivity', false)
            ->where('activity', []),
        );
});

test('adding and removing a team member is logged with the member named', function () {
    $admin = User::factory()->create(['name' => 'Ada Lovelace']);
    $member = User::factory()->create(['name' => 'Grace Hopper']);
    $organization = Organization::factory()->create();
    $organization->addMember($admin, 'Admin');
    $organization->addMember($member, 'Member');
    $team = Team::factory()->for($organization)->create(['name' => 'Development']);

    $this->actingAs($admin)
        ->post(route('teams.members.store', [
            'current_organization' => $organization,
            'team' => $team]), ['user_id' => $member->id]
        )
        ->assertRedirect();

    $this->actingAs($admin)
        ->delete(route('teams.members.destroy', ['current_organization' => $organization, 'team' => $team, 'member' => $member]))
        ->assertRedirect();

    $entries = Activity::query()
        ->where('log_name', 'team_members')
        ->with('causer')
        ->oldest('id')
        ->get();

    expect($entries)->toHaveCount(2);
    expect($entries[0])
        ->event->toBe('created')
        ->subject_type->toBe(TeamMembership::class)
        ->causer_id->toBe($admin->id);
    expect($entries[1])
        ->event->toBe('deleted')
        ->causer_id->toBe($admin->id);
    expect(ActivityResource::make($entries[0])->resolve()['changes'])
        ->toContain(['field' => 'Team', 'from' => null, 'to' => 'Development'])
        ->toContain(['field' => 'Member', 'from' => null, 'to' => 'Grace Hopper']);
});

test('joining and leaving an organization is logged', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Grace Hopper']);
    $organization = Organization::factory()->create(['name' => 'Acme']);
    $organization->addMember($owner, 'Owner');

    $organization->addMember($member, 'Member');
    $membershipId = $organization->memberships()->where('user_id', $member->id)->value('id');
    $organization->removeMember($member);

    $entries = Activity::query()
        ->where('log_name', 'organization_members')
        ->where('subject_id', $membershipId)
        ->with('causer')
        ->oldest('id')
        ->get();

    expect($entries->pluck('event')->all())->toBe(['created', 'deleted']);
    expect(ActivityResource::make($entries[0])->resolve()['changes'])
        ->toContain(['field' => 'Organization', 'from' => null, 'to' => 'Acme'])
        ->toContain(['field' => 'Member', 'from' => null, 'to' => 'Grace Hopper']);
    expect($organization->members()->whereKey($member->id)->exists())->toBeFalse();
});

test('every model records its creation in its own log', function (string $model, string $logName) {
    $subject = $model::factory()->create();

    $entry = Activity::query()->where('log_name', $logName)->where('subject_id', $subject->getKey())->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->event->toBe('created')
        ->subject_type->toBe($model);
})->with([
    'organizations' => [Organization::class, 'organizations'],
    'invitations' => [OrganizationInvitation::class, 'organization_invitations'],
    'comments' => [Comment::class, 'comments'],
    'email addresses' => [EmailAddress::class, 'email_addresses'],
    'users' => [User::class, 'users'],
]);

test('user activity never includes passwords, secrets or tokens', function () {
    $user = User::factory()->create(['name' => 'Grace Hopper']);

    $user->update(['name' => 'Grace Brewster Hopper', 'password' => 'new-secret', 'remember_token' => 'token']);

    $entries = Activity::query()->where('log_name', 'users')->where('subject_id', $user->id)->with('causer')->get();
    $loggedAttributes = $entries->flatMap(fn (Activity $entry) => array_keys($entry->attribute_changes['attributes']))->unique()->values()->all();

    expect($loggedAttributes)->not->toContain('password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes');
    expect(ActivityResource::make($entries->last())->resolve()['changes'])
        ->toBe([['field' => 'Name', 'from' => 'Grace Hopper', 'to' => 'Grace Brewster Hopper']]);
});

test('turning an internal comment public logs the new value as No', function () {
    $comment = Comment::factory()->internal()->create();

    $comment->update(['is_internal' => false]);

    $entry = Activity::query()->where('log_name', 'comments')->where('subject_id', $comment->id)->latest('id')->first();

    expect(ActivityResource::make($entry)->resolve()['changes'])
        ->toBe([['field' => 'Internal', 'from' => 'Yes', 'to' => 'No']]);
});

test('merging a team that owns nothing logs the counts as zero', function () {
    $organization = Organization::factory()->create();
    $source = Team::factory()->for($organization)->create(['name' => 'Marketing']);
    $target = Team::factory()->for($organization)->create(['name' => 'Development']);

    $source->mergeInto($target);

    $entry = Activity::query()->where('log_name', 'teams')->where('event', 'merged')->latest('id')->first();

    expect(ActivityResource::make($entry)->resolve()['changes'])
        ->toContain(['field' => 'Merged from', 'from' => null, 'to' => 'Marketing'])
        ->toContain(['field' => 'Members added', 'from' => null, 'to' => '0'])
        ->toContain(['field' => 'Projects moved', 'from' => null, 'to' => '0'])
        ->toContain(['field' => 'Sprints moved', 'from' => null, 'to' => '0'])
        ->toContain(['field' => 'Issues moved', 'from' => null, 'to' => '0']);
});
