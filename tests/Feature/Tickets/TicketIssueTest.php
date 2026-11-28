<?php

use App\Enums\IssueStatus;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReadyToReply;
use Illuminate\Support\Facades\Notification;

function issueDesk(): array
{
    $organization = Organization::factory()->create();
    config(['services.resend.support_organization' => $organization->slug]);
    $agent = User::factory()->create();
    $organization->addMember($agent, 'Admin');
    $client = Client::factory()->for($organization)->create();

    return [$organization, $agent, $client];
}

test('converting a ticket creates a backlog issue in the client\'s project and makes the agent its reporter', function () {
    [$organization, $agent, $client] = issueDesk();
    $project = Project::factory()->for($organization)->create(['client_id' => $client->id]);
    $ticket = Ticket::factory()->forClient($client)->create(['subject' => 'Export broken']);
    Comment::factory()->for($ticket, 'commentable')->create(['body' => '<p>Clicking export does nothing.</p>']);

    $this->actingAs($agent)
        ->post(route('tickets.issue.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['project_id' => $project->id])
        ->assertRedirect();

    $issue = $project->issues()->sole();

    expect($issue)
        ->title->toBe('Export broken')
        ->description->toBe('<p>Clicking export does nothing.</p>')
        ->status->toBe(IssueStatus::Backlog);
    expect($ticket->fresh())
        ->issue_id->toBe($issue->id)
        ->reporter_id->toBe($agent->id);
});

test('several tickets link to one existing issue', function () {
    [$organization, $agent, $client] = issueDesk();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $tickets = Ticket::factory()->count(2)->forClient($client)->create();

    $tickets->each(fn (Ticket $ticket) => $this->actingAs($agent)
        ->post(route('tickets.issue.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['issue_id' => $issue->id])
        ->assertRedirect());

    expect($issue->tickets()->count())->toBe(2);
});

test('a ticket assigned to neither a client nor the organization cannot be converted or linked', function () {
    [$organization, $agent] = issueDesk();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $ticket = Ticket::factory()->for($organization)->create();

    $this->actingAs($agent)
        ->post(route('tickets.issue.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['issue_id' => $issue->id])
        ->assertSessionHasErrors(['client_id' => "Assign this ticket to a client or to {$organization->name} first."]);

    expect($ticket->fresh()->issue_id)->toBeNull();
});

test('a ticket cannot be linked to another organization\'s issue', function () {
    [$organization, $agent, $client] = issueDesk();
    $ticket = Ticket::factory()->forClient($client)->create();

    $this->actingAs($agent)
        ->post(route('tickets.issue.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['issue_id' => Issue::factory()->create()->id])
        ->assertSessionHasErrors('issue_id');
});

test('finishing an issue marks its open tickets ready to reply and tells each reporter once', function () {
    Notification::fake();
    [$organization, $agent, $client] = issueDesk();
    $otherAgent = User::factory()->create();
    $organization->addMember($otherAgent, 'Admin');
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $mine = Ticket::factory()->count(2)->forClient($client)->create(['issue_id' => $issue->id, 'reporter_id' => $agent->id]);
    $theirs = Ticket::factory()->forClient($client)->create(['issue_id' => $issue->id, 'reporter_id' => $otherAgent->id]);
    $closed = Ticket::factory()->forClient($client)->create(['issue_id' => $issue->id, 'reporter_id' => $agent->id, 'status' => TicketStatus::Closed]);

    $issue->changeStatus(IssueStatus::Done);

    expect($mine->every(fn (Ticket $ticket) => $ticket->fresh()->issue_resolved_at !== null))->toBeTrue()
        ->and($theirs->fresh()->issue_resolved_at)->not->toBeNull()
        ->and($closed->fresh()->issue_resolved_at)->toBeNull();
    Notification::assertSentToTimes($agent, TicketReadyToReply::class, 1);
    Notification::assertSentTo($agent, TicketReadyToReply::class, fn (TicketReadyToReply $notification) => $notification->tickets->count() === 2);
    Notification::assertSentToTimes($otherAgent, TicketReadyToReply::class, 1);
});

test('replying to the customer clears the ready to reply marker', function () {
    Notification::fake();
    [$organization, $agent, $client] = issueDesk();
    $ticket = Ticket::factory()->forClient($client)->create(['issue_resolved_at' => now()]);

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['body' => 'Fixed!'])
        ->assertRedirect();

    expect($ticket->fresh()->issue_resolved_at)->toBeNull();
});

test('the issue page lists the tickets linked to it for ticket managers', function () {
    [$organization, $agent, $client] = issueDesk();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    Ticket::factory()->forClient($client)->create(['issue_id' => $issue->id]);
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($agent)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn ($page) => $page->has('tickets', 1));

    $this->actingAs($member)
        ->get(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]))
        ->assertInertia(fn ($page) => $page->has('tickets', 0));
});

test('a ticket assigned to the organization converts into a project that has no client', function () {
    [$organization, $agent, $client] = issueDesk();
    $internalProject = Project::factory()->for($organization)->create(['client_id' => null]);
    $clientProject = Project::factory()->for($organization)->create(['client_id' => $client->id]);
    $ticket = Ticket::factory()->for($organization)->create(['is_internal' => true]);

    $this->actingAs($agent)
        ->post(route('tickets.issue.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['project_id' => $clientProject->id])
        ->assertSessionHasErrors('project_id');

    $this->actingAs($agent)
        ->post(route('tickets.issue.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['project_id' => $internalProject->id])
        ->assertRedirect();

    expect($ticket->fresh()->issue_id)->toBe($internalProject->issues()->sole()->id);
});
