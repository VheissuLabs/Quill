<?php

use App\Events\ModulesChanged;
use App\Models\Organization;
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
        'feature_voting_enabled' => false,
        'feature_voting_boards_enabled' => false,
        'feature_voting_voting_enabled' => false,
        'development_enabled' => true,
        'development_issues_enabled' => true,
        'development_sprints_enabled' => true,
        'development_workflow_enabled' => true,
        'teams_enabled' => true,
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
