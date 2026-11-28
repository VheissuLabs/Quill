<?php

use App\Events\FeedbackCommented;
use App\Events\IssuesChanged;
use App\Events\TicketsChanged;
use App\Models\Comment;
use App\Models\EmailMessage;
use App\Models\Feedback;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

function liveListDesk(): array
{
    $organization = Organization::factory()->create();
    config(['services.resend.support_organization' => $organization->slug]);
    $agent = User::factory()->create();
    $organization->addMember($agent, 'Admin');

    return [$organization, $agent];
}

function liveListeners(string $url, ?User $user, string $prop): array
{
    $request = $user ? test()->actingAs($user) : test();

    return $request->get($url)->viewData('page')['liveProps'][$prop]['listeners'];
}

test('the inbox shows a window of tickets that widens on request', function () {
    [$organization, $agent] = liveListDesk();
    Ticket::factory()->for($organization)->count(31)->create();
    $inbox = route('tickets.index', ['current_organization' => $organization]);

    $this->actingAs($agent)->get($inbox)
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 30)->where('tickets.hasMore', true));

    $this->actingAs($agent)->get($inbox.'?show=60')
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 31)->where('tickets.hasMore', false));
});

test('ticket lists and comment threads refresh when they change', function () {
    [$organization, $agent] = liveListDesk();
    $ticket = Ticket::factory()->for($organization)->create();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $project = Project::factory()->for($organization)->create(['visibility' => 'public']);
    $feedback = Feedback::factory()->for($project)->create();
    $organizationChannel = ['name' => "organizations.{$organization->id}", 'type' => 'private'];

    expect(liveListeners(route('tickets.index', ['current_organization' => $organization]), $agent, 'tickets'))
        ->toContain(['channel' => $organizationChannel, 'events' => ['tickets.changed', 'issues.changed']])
        ->and(liveListeners(route('tickets.show', ['current_organization' => $organization, 'ticket' => $ticket]), $agent, 'comments'))
        ->toBe([['channel' => $organizationChannel, 'events' => ['tickets.changed']]])
        ->and(liveListeners(route('issues.show', ['current_organization' => $organization, 'issue' => $issue]), $agent, 'comments'))
        ->toBe([['channel' => $organizationChannel, 'events' => ['issues.changed']]])
        ->and(liveListeners(route('portal.feedback.show', ['organization' => $organization, 'project' => $project, 'feedback' => $feedback]), null, 'comments'))
        ->toBe([['channel' => ['name' => "projects.{$project->id}", 'type' => 'public'], 'events' => ['feedback.commented']]]);
});

test('commenting on a ticket or an issue announces it to the organization', function () {
    $organization = Organization::factory()->create();
    $ticket = Ticket::factory()->for($organization)->create();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();

    Event::fake([TicketsChanged::class, IssuesChanged::class, FeedbackCommented::class]);

    Comment::factory()->for($ticket, 'commentable')->create();
    Comment::factory()->for($issue, 'commentable')->create();

    Event::assertDispatched(TicketsChanged::class, fn (TicketsChanged $event) => $event->organizationId === $organization->id);
    Event::assertDispatched(IssuesChanged::class, fn (IssuesChanged $event) => $event->organizationId === $organization->id
        && $event->issueIds === [$issue->id]);
});

test('a reply failing to send announces the ticket', function () {
    $organization = Organization::factory()->create();
    $ticket = Ticket::factory()->for($organization)->create();
    $message = EmailMessage::factory()->for(Comment::factory()->for($ticket, 'commentable'))->outbound()->create();

    Event::fake([TicketsChanged::class]);

    $message->markFailed();

    Event::assertDispatched(TicketsChanged::class, fn (TicketsChanged $event) => $event->organizationId === $organization->id);
});
