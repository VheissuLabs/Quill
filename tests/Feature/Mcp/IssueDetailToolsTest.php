<?php

use App\Enums\IssueLinkType;
use App\Enums\IssueStatus;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Issues\AddIssueCommentTool;
use App\Mcp\Tools\Issues\DeleteIssueCommentTool;
use App\Mcp\Tools\Issues\DeleteIssueLinkTool;
use App\Mcp\Tools\Issues\DeleteIssueTool;
use App\Mcp\Tools\Issues\LinkIssuesTool;
use App\Mcp\Tools\Issues\ReorderIssuesTool;
use App\Mcp\Tools\Issues\ShowIssueTool;
use App\Mcp\Tools\Issues\UpdateIssueLinkTool;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\IssueLink;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

function issueDetailToolMember(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization, Project::factory()->for($organization)->create()];
}

function issueDetailToolComment(Issue $issue, User $author, string $body = 'Looks good'): Comment
{
    return Comment::factory()->create([
        'commentable_type' => $issue->getMorphClass(),
        'commentable_id' => $issue->id,
        'user_id' => $author->id,
        'body' => $body,
    ]);
}

test('it shows an issue with its comments and links', function () {
    [$user, , $project] = issueDetailToolMember();
    $issue = Issue::factory()->for($project)->create(['title' => 'Checkout fails', 'description' => 'On Safari only']);
    $blocker = Issue::factory()->for($project)->create(['title' => 'Upgrade payment SDK']);
    $blocker->linkTo($issue, IssueLinkType::Blocks);
    issueDetailToolComment($issue, $user, 'Reproduced it');

    QuillServer::actingAs($user)->tool(ShowIssueTool::class, ['issue_id' => $issue->id])
        ->assertOk()
        ->assertSee(['Checkout fails', 'On Safari only', 'Upgrade payment SDK', 'Reproduced it']);
});

test('it does not show an issue from another organization', function () {
    [$user] = issueDetailToolMember();
    $foreign = Issue::factory()->create();

    QuillServer::actingAs($user)->tool(ShowIssueTool::class, ['issue_id' => $foreign->id])->assertHasErrors();
});

test('admins can delete an issue', function () {
    [$user, , $project] = issueDetailToolMember();
    $issue = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(DeleteIssueTool::class, ['issue_id' => $issue->id])->assertOk();

    expect($issue->fresh())->toBeNull();
});

test('members without the manage issues permission cannot delete an issue', function () {
    [$user, , $project] = issueDetailToolMember('Member');
    $issue = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(DeleteIssueTool::class, ['issue_id' => $issue->id])->assertHasErrors();

    expect($issue->fresh())->not->toBeNull();
});

test('it links two issues, either way round', function () {
    [$user, , $project] = issueDetailToolMember('Member');
    $child = Issue::factory()->for($project)->create();
    $parent = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(LinkIssuesTool::class, ['issue_id' => $child->id, 'related_issue_id' => $parent->id, 'type' => 'parent', 'inverse' => true])
        ->assertOk()
        ->assertSee($parent->title);

    expect(IssueLink::sole())->issue_id->toBe($parent->id)->related_issue_id->toBe($child->id)->type->toBe(IssueLinkType::Parent);
});

test('it will not link to an issue in another organization', function () {
    [$user, , $project] = issueDetailToolMember();
    $issue = Issue::factory()->for($project)->create();
    $foreign = Issue::factory()->create();

    QuillServer::actingAs($user)->tool(LinkIssuesTool::class, ['issue_id' => $issue->id, 'related_issue_id' => $foreign->id, 'type' => 'relates'])
        ->assertHasErrors();

    expect(IssueLink::exists())->toBeFalse();
});

test('it recasts and removes a link', function () {
    [$user, , $project] = issueDetailToolMember('Member');
    $issue = Issue::factory()->for($project)->create();
    $other = Issue::factory()->for($project)->create();
    $link = $issue->linkTo($other, IssueLinkType::Relates);

    QuillServer::actingAs($user)->tool(UpdateIssueLinkTool::class, ['issue_id' => $issue->id, 'link_id' => $link->id, 'type' => 'blocks', 'inverse' => true])->assertOk();

    $recast = IssueLink::sole();
    expect($recast)->issue_id->toBe($other->id)->type->toBe(IssueLinkType::Blocks);

    QuillServer::actingAs($user)->tool(DeleteIssueLinkTool::class, ['issue_id' => $issue->id, 'link_id' => $recast->id])->assertOk();

    expect(IssueLink::exists())->toBeFalse();
});

test('a link can only be changed from one of the issues it joins', function () {
    [$user, , $project] = issueDetailToolMember();
    $bystander = Issue::factory()->for($project)->create();
    $link = Issue::factory()->for($project)->create()->linkTo(Issue::factory()->for($project)->create(), IssueLinkType::Relates);

    QuillServer::actingAs($user)->tool(DeleteIssueLinkTool::class, ['issue_id' => $bystander->id, 'link_id' => $link->id])->assertHasErrors();

    expect($link->fresh())->not->toBeNull();
});

test('it comments on an issue and runs slash commands', function () {
    [$user, , $project] = issueDetailToolMember('Member');
    $issue = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(AddIssueCommentTool::class, ['issue_id' => $issue->id, 'body' => "Fixed in the release\n/status done"])
        ->assertOk()
        ->assertSee('Fixed in the release');

    expect($issue->comments()->sole())->body->toBe('Fixed in the release')->user_id->toBe($user->id);
    expect($issue->fresh()->status)->toBe(IssueStatus::Done);
});

test('it replies to a comment', function () {
    [$user, , $project] = issueDetailToolMember('Member');
    $issue = Issue::factory()->for($project)->create();
    $parent = issueDetailToolComment($issue, $user);

    QuillServer::actingAs($user)->tool(AddIssueCommentTool::class, ['issue_id' => $issue->id, 'body' => 'Agreed', 'parent_id' => $parent->id])->assertOk();

    expect($issue->comments()->where('body', 'Agreed')->sole()->parent_id)->toBe($parent->id);
});

test('commenting uses the web validation', function () {
    [$user, , $project] = issueDetailToolMember();
    $issue = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(AddIssueCommentTool::class, ['issue_id' => $issue->id, 'body' => str_repeat('a', 5001)])
        ->assertHasErrors(['body']);
});

test('members can delete their own comments but not other people\'s', function () {
    [$user, $organization, $project] = issueDetailToolMember('Member');
    $issue = Issue::factory()->for($project)->create();
    $own = issueDetailToolComment($issue, $user);
    $colleague = User::factory()->create();
    $organization->addMember($colleague, 'Member');
    $theirs = issueDetailToolComment($issue, $colleague);

    QuillServer::actingAs($user)->tool(DeleteIssueCommentTool::class, ['issue_id' => $issue->id, 'comment_id' => $own->id])->assertOk();
    QuillServer::actingAs($user)->tool(DeleteIssueCommentTool::class, ['issue_id' => $issue->id, 'comment_id' => $theirs->id])->assertHasErrors();

    expect($own->fresh())->toBeNull();
    expect($theirs->fresh())->not->toBeNull();
});

test('it reorders issues', function () {
    [$user, , $project] = issueDetailToolMember('Member');
    $first = Issue::factory()->for($project)->create();
    $second = Issue::factory()->for($project)->create();

    QuillServer::actingAs($user)->tool(ReorderIssuesTool::class, ['issue_ids' => [$second->id, $first->id]])->assertOk();

    expect($second->fresh()->position)->toBe(0);
    expect($first->fresh()->position)->toBe(1);
});

test('it will not reorder issues from another organization', function () {
    [$user] = issueDetailToolMember();
    $foreign = Issue::factory()->create(['position' => 5]);

    QuillServer::actingAs($user)->tool(ReorderIssuesTool::class, ['issue_ids' => [$foreign->id]])->assertHasErrors();

    expect($foreign->fresh()->position)->toBe(5);
});
