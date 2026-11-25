<?php

use App\Enums\FeedbackStatus;
use App\Enums\IssueStatus;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Feedback\AddFeedbackCommentTool;
use App\Mcp\Tools\Feedback\ConvertFeedbackToIssueTool;
use App\Mcp\Tools\Feedback\CreateFeedbackTool;
use App\Mcp\Tools\Feedback\DeleteFeedbackCommentTool;
use App\Mcp\Tools\Feedback\DeleteFeedbackTool;
use App\Mcp\Tools\Feedback\ListFeedbackTool;
use App\Mcp\Tools\Feedback\RemoveFeedbackVoteTool;
use App\Mcp\Tools\Feedback\ShowFeedbackTool;
use App\Mcp\Tools\Feedback\UpdateFeedbackStatusTool;
use App\Mcp\Tools\Feedback\UpdateFeedbackTool;
use App\Mcp\Tools\Feedback\VoteOnFeedbackTool;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function feedbackToolMember(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization, Project::factory()->for($organization)->create(['name' => 'Acme Site'])];
}

function feedbackToolColleague(Organization $organization, string $role = 'Member'): User
{
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return $user;
}

test('it lists the active feedback on a board, filtered like the portal', function () {
    [$user, , $project] = feedbackToolMember();
    Feedback::factory()->for($project)->create(['title' => 'Dark mode']);
    Feedback::factory()->for($project)->bug()->create(['title' => 'Login crash']);
    Feedback::factory()->for($project)->create(['title' => 'Old idea', 'status' => FeedbackStatus::Declined]);

    QuillServer::actingAs($user)->tool(ListFeedbackTool::class, ['project' => $project->slug])
        ->assertOk()
        ->assertSee(['Dark mode', 'Login crash'])
        ->assertDontSee('Old idea');

    QuillServer::actingAs($user)->tool(ListFeedbackTool::class, ['project' => $project->slug, 'type' => 'bug'])
        ->assertOk()
        ->assertSee('Login crash')
        ->assertDontSee('Dark mode');

    QuillServer::actingAs($user)->tool(ListFeedbackTool::class, ['project' => $project->slug, 'status' => 'declined', 'search' => 'old'])
        ->assertOk()
        ->assertSee('Old idea')
        ->assertDontSee('Dark mode');
});

test('it cannot list feedback in another organization', function () {
    [$user] = feedbackToolMember();
    $foreign = Project::factory()->create();
    Feedback::factory()->for($foreign)->create(['title' => 'Secret plan']);

    QuillServer::actingAs($user)->tool(ListFeedbackTool::class, ['organization' => $foreign->organization->slug, 'project' => $foreign->slug])
        ->assertHasErrors()
        ->assertDontSee('Secret plan');
});

test('it shows feedback with its comments and internal notes', function () {
    [$user, , $project] = feedbackToolMember();
    $feedback = Feedback::factory()->for($project)->create(['title' => 'Dark mode']);
    Comment::factory()->for($feedback, 'commentable')->create(['body' => 'Yes please']);
    Comment::factory()->for($feedback, 'commentable')->internal()->create(['body' => 'Next quarter']);

    QuillServer::actingAs($user)->tool(ShowFeedbackTool::class, ['feedback_id' => $feedback->id])
        ->assertOk()
        ->assertSee(['Dark mode', 'Yes please', 'Next quarter']);
});

test('it cannot show feedback from another organization by id', function () {
    [$user] = feedbackToolMember();
    $foreign = Feedback::factory()->create(['title' => 'Secret plan']);

    QuillServer::actingAs($user)->tool(ShowFeedbackTool::class, ['feedback_id' => $foreign->id])
        ->assertHasErrors()
        ->assertDontSee('Secret plan');
});

test('it creates feedback with the author voting for it', function () {
    [$user, , $project] = feedbackToolMember('Member');

    QuillServer::actingAs($user)->tool(CreateFeedbackTool::class, ['project' => $project->slug, 'title' => 'Dark mode', 'type' => 'feature'])
        ->assertOk()
        ->assertSee('Dark mode');

    $feedback = $project->feedback()->sole();

    expect($feedback)->user_id->toBe($user->id)->status->toBe(FeedbackStatus::Open)
        ->and($feedback->votes()->where('user_id', $user->id)->value('direction'))->toBe(1);
});

test('creating feedback uses the web validation', function () {
    [$user, , $project] = feedbackToolMember();

    QuillServer::actingAs($user)->tool(CreateFeedbackTool::class, ['project' => $project->slug, 'title' => str_repeat('a', 300)])
        ->assertHasErrors(['title']);

    expect(Feedback::query()->exists())->toBeFalse();
});

test('authors update their own feedback, sending only the fields to change', function () {
    [, $organization, $project] = feedbackToolMember();
    $author = feedbackToolColleague($organization);
    $feedback = Feedback::factory()->for($project)->for($author, 'author')->create(['title' => 'Dark mode', 'description' => 'Please.']);

    QuillServer::actingAs($author)->tool(UpdateFeedbackTool::class, ['feedback_id' => $feedback->id, 'title' => 'Dark theme'])->assertOk();

    expect($feedback->fresh())->title->toBe('Dark theme')->description->toBe('Please.');
});

test('members cannot update or delete someone else\'s feedback', function () {
    [, $organization, $project] = feedbackToolMember();
    $member = feedbackToolColleague($organization);
    $feedback = Feedback::factory()->for($project)->create(['title' => 'Dark mode']);

    QuillServer::actingAs($member)->tool(UpdateFeedbackTool::class, ['feedback_id' => $feedback->id, 'title' => 'Hijacked'])->assertHasErrors(['This action is unauthorized.']);
    QuillServer::actingAs($member)->tool(DeleteFeedbackTool::class, ['feedback_id' => $feedback->id])->assertHasErrors();

    expect($feedback->fresh())->not->toBeNull()->title->toBe('Dark mode');
});

test('feedback managers delete feedback', function () {
    [$user, , $project] = feedbackToolMember();
    $feedback = Feedback::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(DeleteFeedbackTool::class, ['feedback_id' => $feedback->id])->assertOk();

    expect($feedback->fresh())->toBeNull();
});

test('feedback managers change the status', function () {
    [$user, , $project] = feedbackToolMember();
    $feedback = Feedback::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(UpdateFeedbackStatusTool::class, ['feedback_id' => $feedback->id, 'status' => 'in_progress'])->assertOk();

    expect($feedback->fresh()->status)->toBe(FeedbackStatus::InProgress);
});

test('members without feedback management cannot change the status', function () {
    [, $organization, $project] = feedbackToolMember();
    $member = feedbackToolColleague($organization);
    $feedback = Feedback::factory()->for($project)->for($member, 'author')->create();

    QuillServer::actingAs($member)->tool(UpdateFeedbackStatusTool::class, ['feedback_id' => $feedback->id, 'status' => 'completed'])->assertHasErrors(['This action is unauthorized.']);

    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Open);
});

test('it converts feedback into a backlog issue once', function () {
    [$user, , $project] = feedbackToolMember();
    $feedback = Feedback::factory()->for($project)->create(['title' => 'Dark mode']);

    QuillServer::actingAs($user)->tool(ConvertFeedbackToIssueTool::class, ['feedback_id' => $feedback->id])
        ->assertOk()
        ->assertSee('Dark mode');

    expect($project->issues()->sole())->title->toBe('Dark mode')->status->toBe(IssueStatus::Backlog)->feedback_id->toBe($feedback->id)
        ->and($feedback->fresh()->status)->toBe(FeedbackStatus::Planned);

    QuillServer::actingAs($user)->tool(ConvertFeedbackToIssueTool::class, ['feedback_id' => $feedback->id])->assertHasErrors();

    expect($project->issues()->count())->toBe(1);
});

test('members without feedback management cannot convert feedback', function () {
    [, $organization, $project] = feedbackToolMember();
    $member = feedbackToolColleague($organization);
    $feedback = Feedback::factory()->for($project)->create();

    QuillServer::actingAs($member)->tool(ConvertFeedbackToIssueTool::class, ['feedback_id' => $feedback->id])->assertHasErrors(['This action is unauthorized.']);

    expect($project->issues()->exists())->toBeFalse();
});

test('it votes against feedback and withdraws the vote', function () {
    [$user, , $project] = feedbackToolMember('Member');
    $feedback = Feedback::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(VoteOnFeedbackTool::class, ['feedback_id' => $feedback->id, 'direction' => 'down'])->assertOk();

    expect($feedback->votes()->where('user_id', $user->id)->value('direction'))->toBe(-1);

    QuillServer::actingAs($user)->tool(RemoveFeedbackVoteTool::class, ['feedback_id' => $feedback->id])->assertOk();

    expect($feedback->votes()->exists())->toBeFalse();
});

test('settled feedback takes no votes', function () {
    [$user, , $project] = feedbackToolMember();
    $feedback = Feedback::factory()->for($project)->create(['status' => FeedbackStatus::Completed]);

    QuillServer::actingAs($user)->tool(VoteOnFeedbackTool::class, ['feedback_id' => $feedback->id])->assertHasErrors(['This action is unauthorized.']);

    expect($feedback->votes()->exists())->toBeFalse();
});

test('it comments on feedback and replies in the thread', function () {
    [$user, , $project] = feedbackToolMember('Member');
    $feedback = Feedback::factory()->for($project)->create();
    $parent = Comment::factory()->for($feedback, 'commentable')->create();

    QuillServer::actingAs($user)->tool(AddFeedbackCommentTool::class, ['feedback_id' => $feedback->id, 'body' => 'Me too', 'parent_id' => $parent->id])
        ->assertOk()
        ->assertSee('Me too');

    expect($feedback->comments()->latest('id')->first())->body->toBe('Me too')->parent_id->toBe($parent->id)->user_id->toBe($user->id)->is_internal->toBeFalse();
});

test('a comment that is only a slash command runs it', function () {
    [$user, , $project] = feedbackToolMember();
    $feedback = Feedback::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(AddFeedbackCommentTool::class, ['feedback_id' => $feedback->id, 'body' => '/status planned'])
        ->assertOk()
        ->assertSee('Status set to Planned.');

    expect($feedback->fresh()->status)->toBe(FeedbackStatus::Planned)
        ->and($feedback->comments()->exists())->toBeFalse();
});

test('outsiders on a public board cannot comment through another organization', function () {
    [$user] = feedbackToolMember();
    $foreign = Feedback::factory()->for(Project::factory()->public())->create();

    QuillServer::actingAs($user)->tool(AddFeedbackCommentTool::class, ['organization' => $foreign->project->organization->slug, 'feedback_id' => $foreign->id, 'body' => 'Hi'])
        ->assertHasErrors();

    expect($foreign->comments()->exists())->toBeFalse();
});

test('authors delete their own feedback comments but not other people\'s', function () {
    [, $organization, $project] = feedbackToolMember();
    $member = feedbackToolColleague($organization);
    $feedback = Feedback::factory()->for($project)->create();
    $own = Comment::factory()->for($feedback, 'commentable')->create(['user_id' => $member->id]);
    $others = Comment::factory()->for($feedback, 'commentable')->create();

    QuillServer::actingAs($member)->tool(DeleteFeedbackCommentTool::class, ['feedback_id' => $feedback->id, 'comment_id' => $own->id])->assertOk();
    QuillServer::actingAs($member)->tool(DeleteFeedbackCommentTool::class, ['feedback_id' => $feedback->id, 'comment_id' => $others->id])->assertHasErrors(['This action is unauthorized.']);

    expect($own->fresh())->toBeNull()
        ->and($others->fresh())->not->toBeNull();
});
