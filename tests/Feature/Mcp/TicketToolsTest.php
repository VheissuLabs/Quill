<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\IssueStatus;
use App\Enums\TicketStatus;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Tickets\ConvertTicketToIssueTool;
use App\Mcp\Tools\Tickets\CreateTicketTool;
use App\Mcp\Tools\Tickets\DeleteTicketReplyTool;
use App\Mcp\Tools\Tickets\ListClientContactsTool;
use App\Mcp\Tools\Tickets\ListTicketsTool;
use App\Mcp\Tools\Tickets\ReplyToTicketTool;
use App\Mcp\Tools\Tickets\RetryTicketReplyDeliveryTool;
use App\Mcp\Tools\Tickets\ShowTicketTool;
use App\Mcp\Tools\Tickets\UnlinkTicketIssueTool;
use App\Mcp\Tools\Tickets\UpdateTicketTool;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Comment;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReply;
use Illuminate\Support\Facades\Notification;

function ticketToolAgent(): array
{
    $organization = Organization::factory()->create();
    config([
        'services.resend.support_organization' => $organization->slug,
        'services.resend.support_address' => 'support@vheissulabs.com',
    ]);
    $agent = User::factory()->create();
    $organization->addMember($agent, 'Admin');
    $agent->switchOrganization($organization);

    return [$agent, $organization];
}

function ticketToolMember(Organization $organization): User
{
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $member->switchOrganization($organization);

    return $member;
}

test('it lists the open inbox with the web filters', function () {
    [$agent, $organization] = ticketToolAgent();
    $client = Client::factory()->for($organization)->create();
    Ticket::factory()->for($organization)->create(['subject' => 'Unassigned question']);
    Ticket::factory()->forClient($client)->create(['subject' => 'Acme export']);
    Ticket::factory()->for($organization)->create(['subject' => 'Old closed thing', 'status' => TicketStatus::Closed]);

    QuillServer::actingAs($agent)->tool(ListTicketsTool::class)
        ->assertOk()
        ->assertSee(['Unassigned question', 'Acme export'])
        ->assertDontSee('Old closed thing');

    QuillServer::actingAs($agent)->tool(ListTicketsTool::class, ['view' => 'unassigned'])
        ->assertOk()
        ->assertSee('Unassigned question')
        ->assertDontSee('Acme export');

    QuillServer::actingAs($agent)->tool(ListTicketsTool::class, ['client' => $client->slug])
        ->assertOk()
        ->assertSee('Acme export')
        ->assertDontSee('Unassigned question');

    QuillServer::actingAs($agent)->tool(ListTicketsTool::class, ['status' => 'closed'])
        ->assertOk()
        ->assertSee('Old closed thing')
        ->assertDontSee('Acme export');
});

test('members without ticket management cannot list or show tickets', function () {
    [, $organization] = ticketToolAgent();
    $member = ticketToolMember($organization);
    $ticket = Ticket::factory()->for($organization)->create(['subject' => 'Private matter']);

    QuillServer::actingAs($member)->tool(ListTicketsTool::class)->assertHasErrors(['This action is unauthorized.']);
    QuillServer::actingAs($member)->tool(ShowTicketTool::class, ['ticket_id' => $ticket->id])
        ->assertHasErrors(['This action is unauthorized.'])
        ->assertDontSee('Private matter');
});

test('it shows a ticket with its replies and attachment details', function () {
    [$agent, $organization] = ticketToolAgent();
    $ticket = Ticket::factory()->for($organization)->create(['subject' => 'Export broken']);
    $comment = Comment::factory()->for($ticket, 'commentable')->create(['user_id' => null, 'body' => 'Clicking export does nothing']);
    $message = EmailMessage::factory()->for($comment)->create();
    EmailAttachment::factory()->for($message)->create(['filename' => 'export-error.png']);

    QuillServer::actingAs($agent)->tool(ShowTicketTool::class, ['ticket_id' => $ticket->id])
        ->assertOk()
        ->assertSee(['Export broken', 'Clicking export does nothing', 'export-error.png']);
});

test('it cannot show a ticket from another organization', function () {
    [$agent] = ticketToolAgent();
    $foreign = Ticket::factory()->create(['subject' => 'Someone else']);

    QuillServer::actingAs($agent)->tool(ShowTicketTool::class, ['ticket_id' => $foreign->id])
        ->assertHasErrors()
        ->assertDontSee('Someone else');
});

test('it opens a ticket for an existing contact and keeps the body internal unless sent', function () {
    Notification::fake();
    [$agent, $organization] = ticketToolAgent();
    $client = Client::factory()->for($organization)->create();
    $contact = ClientContact::factory()->for($client)->create(['email' => 'jane@acme.test']);

    QuillServer::actingAs($agent)->tool(CreateTicketTool::class, ['client_contact_id' => $contact->id, 'subject' => 'Called about export', 'body' => 'She says export fails.'])
        ->assertOk()
        ->assertSee('Called about export');

    $ticket = $organization->tickets()->sole();

    expect($ticket)->client_id->toBe($client->id)->requester_email->toBe('jane@acme.test')->created_by->toBe($agent->id)
        ->and($ticket->comments()->sole()->is_internal)->toBeTrue();
    Notification::assertNothingSent();
});

test('opening a ticket with send emails the customer', function () {
    Notification::fake();
    [$agent, $organization] = ticketToolAgent();

    QuillServer::actingAs($agent)->tool(CreateTicketTool::class, [
        'requester_name' => 'Jane',
        'requester_email' => 'jane@acme.test',
        'subject' => 'Following up',
        'body' => 'Is export working now?',
        'send' => true,
    ])->assertOk();

    expect($organization->tickets()->sole()->status)->toBe(TicketStatus::AwaitingCustomer);
    Notification::assertSentOnDemand(TicketReply::class);
});

test('opening a ticket uses the web validation', function () {
    [$agent, $organization] = ticketToolAgent();

    QuillServer::actingAs($agent)->tool(CreateTicketTool::class, ['subject' => 'No requester', 'body' => 'Hi'])
        ->assertHasErrors(['Pick a contact or enter the customer\'s email.']);

    expect($organization->tickets()->exists())->toBeFalse();
});

test('it assigns a ticket to a client and changes its status', function () {
    [$agent, $organization] = ticketToolAgent();
    $client = Client::factory()->for($organization)->create();
    $ticket = Ticket::factory()->for($organization)->create();

    QuillServer::actingAs($agent)->tool(UpdateTicketTool::class, ['ticket_id' => $ticket->id, 'client_id' => $client->id, 'status' => 'resolved'])->assertOk();

    expect($ticket->fresh())->client_id->toBe($client->id)->status->toBe(TicketStatus::Resolved);

    QuillServer::actingAs($agent)->tool(UpdateTicketTool::class, ['ticket_id' => $ticket->id, 'client_id' => null])->assertOk();

    expect($ticket->fresh())->client_id->toBeNull()->status->toBe(TicketStatus::Resolved);
});

test('a ticket cannot be assigned to another organization\'s client', function () {
    [$agent, $organization] = ticketToolAgent();
    $ticket = Ticket::factory()->for($organization)->create();

    QuillServer::actingAs($agent)->tool(UpdateTicketTool::class, ['ticket_id' => $ticket->id, 'client_id' => Client::factory()->create()->id])
        ->assertHasErrors(['client id']);

    expect($ticket->fresh()->client_id)->toBeNull();
});

test('a reply is emailed to the requester', function () {
    Notification::fake();
    [$agent, $organization] = ticketToolAgent();
    $ticket = Ticket::factory()->for($organization)->create(['requester_email' => 'jane@acme.test']);

    QuillServer::actingAs($agent)->tool(ReplyToTicketTool::class, ['ticket_id' => $ticket->id, 'body' => 'Fixed, try again.'])
        ->assertOk()
        ->assertSee('Fixed, try again.');

    $reply = $ticket->comments()->sole();

    expect($reply)->is_internal->toBeFalse()->user_id->toBe($agent->id)
        ->and($reply->emailMessage->delivery_status)->toBe(EmailDeliveryStatus::Queued)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::AwaitingCustomer);
    Notification::assertSentOnDemand(TicketReply::class);
});

test('an internal note is never emailed', function () {
    Notification::fake();
    [$agent, $organization] = ticketToolAgent();
    $ticket = Ticket::factory()->for($organization)->create();

    QuillServer::actingAs($agent)->tool(ReplyToTicketTool::class, ['ticket_id' => $ticket->id, 'body' => 'Old plan.', 'internal' => true])->assertOk();

    expect($ticket->comments()->sole())->is_internal->toBeTrue()->emailMessage->toBeNull();
    Notification::assertNothingSent();
});

test('members without ticket management cannot reply', function () {
    Notification::fake();
    [, $organization] = ticketToolAgent();
    $member = ticketToolMember($organization);
    $ticket = Ticket::factory()->for($organization)->create();

    QuillServer::actingAs($member)->tool(ReplyToTicketTool::class, ['ticket_id' => $ticket->id, 'body' => 'Hi'])
        ->assertHasErrors(['This action is unauthorized.']);

    expect($ticket->comments()->exists())->toBeFalse();
    Notification::assertNothingSent();
});

test('it deletes internal notes but not emails', function () {
    [$agent, $organization] = ticketToolAgent();
    $ticket = Ticket::factory()->for($organization)->create();
    $note = Comment::factory()->for($ticket, 'commentable')->internal()->create();
    $email = Comment::factory()->for($ticket, 'commentable')->create();

    QuillServer::actingAs($agent)->tool(DeleteTicketReplyTool::class, ['ticket_id' => $ticket->id, 'comment_id' => $note->id])->assertOk();
    QuillServer::actingAs($agent)->tool(DeleteTicketReplyTool::class, ['ticket_id' => $ticket->id, 'comment_id' => $email->id])
        ->assertHasErrors(['This action is unauthorized.']);

    expect($note->fresh())->toBeNull()
        ->and($email->fresh())->not->toBeNull();
});

test('a failed reply is sent again', function () {
    Notification::fake();
    [$agent, $organization] = ticketToolAgent();
    $ticket = Ticket::factory()->for($organization)->create();
    $comment = Comment::factory()->for($ticket, 'commentable')->create(['user_id' => $agent->id]);
    $message = EmailMessage::factory()->for($comment)->outbound()->create(['delivery_status' => EmailDeliveryStatus::Failed]);

    QuillServer::actingAs($agent)->tool(RetryTicketReplyDeliveryTool::class, ['ticket_id' => $ticket->id, 'comment_id' => $comment->id])->assertOk();

    expect($message->fresh()->delivery_status)->toBe(EmailDeliveryStatus::Queued);
    Notification::assertSentOnDemand(TicketReply::class);
});

test('a delivered reply cannot be sent twice', function () {
    Notification::fake();
    [$agent, $organization] = ticketToolAgent();
    $ticket = Ticket::factory()->for($organization)->create();
    $comment = Comment::factory()->for($ticket, 'commentable')->create();
    EmailMessage::factory()->for($comment)->outbound()->create(['delivery_status' => EmailDeliveryStatus::Sent]);

    QuillServer::actingAs($agent)->tool(RetryTicketReplyDeliveryTool::class, ['ticket_id' => $ticket->id, 'comment_id' => $comment->id])
        ->assertHasErrors(['This action is unauthorized.']);

    Notification::assertNothingSent();
});

test('it converts a ticket into a backlog issue in the client\'s project', function () {
    [$agent, $organization] = ticketToolAgent();
    $client = Client::factory()->for($organization)->create();
    $project = Project::factory()->for($organization)->create(['client_id' => $client->id]);
    $ticket = Ticket::factory()->forClient($client)->create(['subject' => 'Export broken']);
    Comment::factory()->for($ticket, 'commentable')->create(['body' => '<p>Clicking export does nothing.</p>']);

    QuillServer::actingAs($agent)->tool(ConvertTicketToIssueTool::class, ['ticket_id' => $ticket->id, 'project_id' => $project->id])
        ->assertOk()
        ->assertSee('Export broken');

    $issue = $project->issues()->sole();

    expect($issue)->title->toBe('Export broken')->status->toBe(IssueStatus::Backlog)
        ->and($ticket->fresh())->issue_id->toBe($issue->id)->reporter_id->toBe($agent->id);
});

test('it links a ticket to an existing issue and unlinks it', function () {
    [$agent, $organization] = ticketToolAgent();
    $client = Client::factory()->for($organization)->create();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $ticket = Ticket::factory()->forClient($client)->create();

    QuillServer::actingAs($agent)->tool(ConvertTicketToIssueTool::class, ['ticket_id' => $ticket->id, 'issue_id' => $issue->id])->assertOk();

    expect($ticket->fresh()->issue_id)->toBe($issue->id);

    QuillServer::actingAs($agent)->tool(UnlinkTicketIssueTool::class, ['ticket_id' => $ticket->id])->assertOk();

    expect($ticket->fresh()->issue_id)->toBeNull()
        ->and($issue->fresh())->not->toBeNull();
});

test('a ticket without a client cannot be converted, nor linked to another organization\'s issue', function () {
    [$agent, $organization] = ticketToolAgent();
    $unassigned = Ticket::factory()->for($organization)->create();
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $assigned = Ticket::factory()->forClient(Client::factory()->for($organization)->create())->create();

    QuillServer::actingAs($agent)->tool(ConvertTicketToIssueTool::class, ['ticket_id' => $unassigned->id, 'issue_id' => $issue->id])
        ->assertHasErrors(['Assign this ticket to a client first.']);
    QuillServer::actingAs($agent)->tool(ConvertTicketToIssueTool::class, ['ticket_id' => $assigned->id, 'issue_id' => Issue::factory()->create()->id])
        ->assertHasErrors(['issue id']);

    expect($unassigned->fresh()->issue_id)->toBeNull()
        ->and($assigned->fresh()->issue_id)->toBeNull();
});

test('members without ticket management cannot unlink an issue', function () {
    [, $organization] = ticketToolAgent();
    $member = ticketToolMember($organization);
    $issue = Issue::factory()->for(Project::factory()->for($organization))->create();
    $ticket = Ticket::factory()->forClient(Client::factory()->for($organization)->create())->create(['issue_id' => $issue->id]);

    QuillServer::actingAs($member)->tool(UnlinkTicketIssueTool::class, ['ticket_id' => $ticket->id])
        ->assertHasErrors(['This action is unauthorized.']);

    expect($ticket->fresh()->issue_id)->toBe($issue->id);
});

test('it looks up client contacts by name or email', function () {
    [$agent, $organization] = ticketToolAgent();
    $client = Client::factory()->for($organization)->create(['name' => 'Acme']);
    ClientContact::factory()->for($client)->create(['name' => 'Jane Doe', 'email' => 'jane@acme.test']);
    ClientContact::factory()->for($client)->create(['name' => 'Bob Roe', 'email' => 'bob@acme.test']);
    ClientContact::factory()->for(Client::factory())->create(['name' => 'Jane Stranger', 'email' => 'jane@else.test']);

    QuillServer::actingAs($agent)->tool(ListClientContactsTool::class, ['search' => 'jane'])
        ->assertOk()
        ->assertSee(['Jane Doe', 'Acme'])
        ->assertDontSee(['Bob Roe', 'Jane Stranger']);
});

test('members without ticket management cannot look up contacts', function () {
    [, $organization] = ticketToolAgent();
    $member = ticketToolMember($organization);

    QuillServer::actingAs($member)->tool(ListClientContactsTool::class, ['search' => 'jane'])
        ->assertHasErrors(['This action is unauthorized.']);
});
