<?php

use App\Models\Team;
use App\Models\User;

test('a member holding member:remove can remove someone from a team', function () {
    [$organization, $admin] = organizationWith('admin');
    $member = User::factory()->create();

    $team = Team::factory()->heldBy($organization)->create();
    $team->update(['owner_id' => $admin->id]);
    $team->members()->attach($admin);
    $team->members()->attach($member);

    $this->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $member]))
        ->assertRedirect(route('teams.edit', $team));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('a member without member:remove cannot remove anyone', function () {
    [$organization, $plainMember] = organizationWith('member');
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $team = Team::factory()->heldBy($organization)->create();
    $team->update(['owner_id' => $owner->id]);
    $team->members()->attach($owner);
    $team->members()->attach($plainMember);
    $team->members()->attach($member);

    $this->actingAs($plainMember)
        ->delete(route('teams.members.destroy', [$team, $member]))
        ->assertForbidden();

    expect($member->fresh()->belongsToTeam($team))->toBeTrue();
})->note('Team ownership grants nothing on its own now — the permission comes from the organization.');

test('the team owner cannot be removed', function () {
    [$organization, $admin] = organizationWith('admin');

    $team = Team::factory()->heldBy($organization)->create();
    $team->update(['owner_id' => $admin->id]);
    $team->members()->attach($admin);

    $this->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $admin]))
        ->assertForbidden();

    expect($admin->fresh()->belongsToTeam($team))->toBeTrue();
});

test('a removed member falls back to their personal team', function () {
    [$organization, $admin] = organizationWith('admin');
    $member = User::factory()->create();
    $personalTeam = $member->personalTeam();

    $team = Team::factory()->heldBy($organization)->create();
    $team->update(['owner_id' => $admin->id]);
    $team->members()->attach($admin);
    $team->members()->attach($member);

    $member->update(['current_team_id' => $team->id]);

    $this->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $member]));

    expect($member->fresh()->current_team_id)->toEqual($personalTeam->id);
});
