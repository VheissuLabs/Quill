<?php

use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('/for, /against and /withdraw manage the vote and text after the command is the comment', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $url = route('portal.feedback.comments.store', [$project->organization, $project, $feedback]);

    $this->actingAs($user)->post($url, ['body' => '/for'])->assertRedirect();

    expect($feedback->votes()->where('user_id', $user->id)->value('direction'))->toBe(1);
    expect($feedback->comments()->count())->toBe(0);

    $this->actingAs($user)->post($url, ['body' => '/against I think this would slow everyone down.'])->assertRedirect();

    expect($feedback->votes()->where('user_id', $user->id)->value('direction'))->toBe(-1);
    expect($feedback->comments()->pluck('body')->all())->toBe(['I think this would slow everyone down.']);

    $this->actingAs($user)->post($url, ['body' => '/like'])->assertRedirect();

    expect($feedback->votes()->where('user_id', $user->id)->value('direction'))->toBe(1);

    $this->actingAs($user)->post($url, ['body' => '/withdraw'])->assertRedirect();

    expect($feedback->votes()->count())->toBe(0);
});

test('/status changes the status for admins and the remaining text is posted as the comment', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create(['status' => FeedbackStatus::Open]);
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');
    $member = User::factory()->create();
    $project->organization->addMember($member, 'Member');
    $url = route('portal.feedback.comments.store', [$project->organization, $project, $feedback]);

    $this->actingAs($admin)
        ->post($url, ['body' => '/status In progress Starting on this today.'])
        ->assertRedirect();

    expect($feedback->fresh()->status)->toBe(FeedbackStatus::InProgress);
    expect($feedback->comments()->pluck('body')->all())->toBe(['Starting on this today.']);

    $this->actingAs($member)
        ->post($url, ['body' => '/status done'])
        ->assertSessionHasErrors(['body' => "You can't change the status."]);

    $this->actingAs($admin)
        ->post($url, ['body' => '/status shipped'])
        ->assertSessionHasErrors('body');
});

test('/convert creates a backlog issue and /internal posts an internal note', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');
    $url = route('portal.feedback.comments.store', [$project->organization, $project, $feedback]);

    $this->actingAs($admin)
        ->post($url, ['body' => "/convert\n/internal\nPicking this up next sprint."])
        ->assertRedirect();

    expect(Issue::where('feedback_id', $feedback->id)->exists())->toBeTrue();
    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Planned);
    expect($feedback->comments()->firstOrFail())
        ->body->toBe('Picking this up next sprint.')
        ->is_internal->toBeTrue();
});

test('unknown commands are rejected and the available commands follow the viewer abilities', function () {
    $project = Project::factory()->public()->create();
    $feedback = Feedback::factory()->for($project)->create();
    $user = User::factory()->create();
    $admin = User::factory()->create();
    $project->organization->addMember($admin, 'Admin');

    $this->actingAs($user)
        ->post(route('portal.feedback.comments.store', [$project->organization, $project, $feedback]), ['body' => '/explode'])
        ->assertSessionHasErrors(['body' => 'Unknown command /explode.']);

    $this->actingAs($user)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('commands', fn ($commands) => collect($commands)->pluck('name')->all() === ['for', 'against', 'withdraw']));

    $this->actingAs($admin)
        ->get(route('portal.feedback.show', [$project->organization, $project, $feedback]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('commands', fn ($commands) => collect($commands)->pluck('name')->all() === ['for', 'against', 'withdraw', 'internal', 'status', 'convert']));
});
