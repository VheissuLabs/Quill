<?php

use App\Enums\ProjectVisibility;
use App\Events\ModulesChanged;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('a member may listen to their organization channel and a stranger may not', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    require base_path('routes/channels.php');

    $member = User::factory()->create();
    $stranger = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-organizations.{$organization->id}",
        ])
        ->assertOk();

    $this->actingAs($stranger)
        ->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-organizations.{$organization->id}",
        ])
        ->assertForbidden();
});

test('changing the organization modules announces it to the organization', function () {
    Event::fake([ModulesChanged::class]);

    $organization = Organization::factory()->create();

    $organization->updateModuleSettings([
        'feedback_enabled' => false,
        'feedback_boards_enabled' => false,
        'feedback_voting_enabled' => false,
        'development_enabled' => true,
        'development_issues_enabled' => true,
        'development_sprints_enabled' => true,
        'development_workflow_enabled' => true,
        'teams_enabled' => true,
        'billing_enabled' => true,
        'billing_invoices_enabled' => true,
        'billing_billing_cycles_enabled' => true,
    ]);

    Event::assertDispatched(
        ModulesChanged::class,
        fn (ModulesChanged $event) => $event->organizationId === $organization->id
    );
});

test('a team switching sprints off announces it to its organization', function () {
    Event::fake([ModulesChanged::class]);

    $organization = Organization::factory()->create();
    $team = Team::factory()->for($organization)->create();

    $team->updateModuleSettings(['sprints_enabled' => false]);

    Event::assertDispatched(
        ModulesChanged::class,
        fn (ModulesChanged $event) => $event->organizationId === $organization->id
    );
});

test('the announcement rides the organization private channel', function () {
    $organization = Organization::factory()->create();

    $event = new ModulesChanged($organization->id);

    expect($event->broadcastOn()->name)->toBe("private-organizations.{$organization->id}")
        ->and($event->broadcastAs())->toBe('modules.changed');
});

test('presence on a feedback is open to anyone who may see the project and closed to everyone else', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    require base_path('routes/channels.php');

    $organization = Organization::factory()->create();
    $project = Project::factory()->for($organization)->create([
        'visibility' => ProjectVisibility::Private,
    ]);
    $feedback = Feedback::factory()->for($project)->create();

    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $outsider = User::factory()->create();

    $payload = [
        'socket_id' => '1234.5678',
        'channel_name' => "presence-feedback.{$feedback->id}",
    ];

    $this->actingAs($member)->post('/broadcasting/auth', $payload)->assertOk();
    $this->actingAs($outsider)->post('/broadcasting/auth', $payload)->assertForbidden();
});
