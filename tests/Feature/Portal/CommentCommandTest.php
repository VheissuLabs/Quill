<?php

use App\Enums\FeatureStatus;
use App\Models\Feature;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('/for, /against and /withdraw manage the vote and text after the command is the comment', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $url = route('portal.features.comments.store', [$project->organization, $project, $feature]);

    $this->actingAs($user)->post($url, ['body' => '/for'])->assertRedirect();

    expect($feature->votes()->where('user_id', $user->id)->value('direction'))->toBe(1);
    expect($feature->comments()->count())->toBe(0);

    $this->actingAs($user)->post($url, ['body' => '/against I think this would slow everyone down.'])->assertRedirect();

    expect($feature->votes()->where('user_id', $user->id)->value('direction'))->toBe(-1);
    expect($feature->comments()->pluck('body')->all())->toBe(['I think this would slow everyone down.']);

    $this->actingAs($user)->post($url, ['body' => '/like'])->assertRedirect();

    expect($feature->votes()->where('user_id', $user->id)->value('direction'))->toBe(1);

    $this->actingAs($user)->post($url, ['body' => '/withdraw'])->assertRedirect();

    expect($feature->votes()->count())->toBe(0);
});

test('/status changes the status for admins and the remaining text is posted as the comment', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create(['status' => FeatureStatus::Open]);
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');
    $url = route('portal.features.comments.store', [$project->organization, $project, $feature]);

    $this->actingAs($admin)
        ->post($url, ['body' => '/status In progress Starting on this today.'])
        ->assertRedirect();

    expect($feature->fresh()->status)->toBe(FeatureStatus::InProgress);
    expect($feature->comments()->pluck('body')->all())->toBe(['Starting on this today.']);

    $this->actingAs($member)
        ->post($url, ['body' => '/status done'])
        ->assertSessionHasErrors(['body' => "You can't change the status."]);

    $this->actingAs($admin)
        ->post($url, ['body' => '/status shipped'])
        ->assertSessionHasErrors('body');
});

test('/convert creates a backlog issue and /internal posts an internal note', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');
    $url = route('portal.features.comments.store', [$project->organization, $project, $feature]);

    $this->actingAs($admin)
        ->post($url, ['body' => "/convert\n/internal\nPicking this up next sprint."])
        ->assertRedirect();

    expect(Issue::where('feature_id', $feature->id)->exists())->toBeTrue();
    expect($feature->fresh()->status)->toBe(FeatureStatus::Planned);
    expect($feature->comments()->firstOrFail())
        ->body->toBe('Picking this up next sprint.')
        ->is_internal->toBeTrue();
});

test('unknown commands are rejected and the available commands follow the viewer abilities', function () {
    $project = Project::factory()->public()->create();
    $feature = Feature::factory()->for($project)->create();
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');

    $this->actingAs($user)
        ->post(route('portal.features.comments.store', [$project->organization, $project, $feature]), ['body' => '/explode'])
        ->assertSessionHasErrors(['body' => 'Unknown command /explode.']);

    $this->actingAs($user)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('commands', fn ($commands) => collect($commands)->pluck('name')->all() === ['for', 'against', 'withdraw']));

    $this->actingAs($admin)
        ->get(route('portal.features.show', [$project->organization, $project, $feature]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('commands', fn ($commands) => collect($commands)->pluck('name')->all() === ['for', 'against', 'withdraw', 'internal', 'status', 'convert']));
});
