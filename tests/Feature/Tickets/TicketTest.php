<?php

use App\Enums\TicketStatus;
use App\Events\TicketsChanged;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function ticketDesk(): array
{
    $organization = Organization::factory()->create();
    config([
        'services.resend.support_organization' => $organization->slug,
        'services.resend.support_address' => 'support@vheissulabs.com',
    ]);
    $agent = User::factory()->create();
    $organization->addMember($agent, 'Admin');

    return [$organization, $agent];
}

test('the inbox lists open tickets, newest conversation first', function () {
    [$organization, $agent] = ticketDesk();
    $older = Ticket::factory()->for($organization)->create(['last_message_at' => now()->subDay()]);
    $newer = Ticket::factory()->for($organization)->create(['last_message_at' => now()]);
    Ticket::factory()->for($organization)->create(['status' => TicketStatus::Closed]);

    $this->actingAs($agent)
        ->get(route('tickets.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Index')
            ->has('tickets.data', 2)
            ->where('tickets.data.0.id', $newer->id)
            ->where('tickets.data.1.id', $older->id));
});

test('the unassigned view shows only tickets without a client', function () {
    [$organization, $agent] = ticketDesk();
    $unassigned = Ticket::factory()->for($organization)->create();
    Ticket::factory()->forClient(Client::factory()->for($organization)->create())->create();

    $this->actingAs($agent)
        ->get(route('tickets.index', ['current_organization' => $organization, 'view' => 'unassigned']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $unassigned->id)
            ->where('counts.unassigned', 1));
});

test('the ticket page shows the conversation', function () {
    [$organization, $agent] = ticketDesk();
    $ticket = Ticket::factory()->for($organization)->create(['requester_email' => 'jane@acme.test']);

    $this->actingAs($agent)
        ->get(route('tickets.show', ['current_organization' => $organization, 'ticket' => $ticket]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Show')
            ->where('ticket.reference', "T-{$ticket->number}")
            ->where('ticket.suggestedDomain', 'acme.test'));
});

test('members without ticket management cannot open the inbox', function () {
    [$organization] = ticketDesk();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');

    $this->actingAs($member)
        ->get(route('tickets.index', ['current_organization' => $organization]))
        ->assertForbidden();
});

test('organizations other than the support inbox have no tickets', function () {
    ticketDesk();
    $elsewhere = Organization::factory()->create();
    $admin = User::factory()->create();
    $elsewhere->addMember($admin, 'Admin');

    $this->actingAs($admin)
        ->get(route('tickets.index', ['current_organization' => $elsewhere]))
        ->assertForbidden();
});

test('a ticket from another organization is not found', function () {
    [$organization, $agent] = ticketDesk();
    $foreign = Ticket::factory()->create();

    $this->actingAs($agent)
        ->get(route('tickets.show', ['current_organization' => $organization, 'ticket' => $foreign]))
        ->assertNotFound();
});

test('an agent assigns an unassigned ticket to a client and changes its status', function () {
    [$organization, $agent] = ticketDesk();
    $client = Client::factory()->for($organization)->create();
    $ticket = Ticket::factory()->for($organization)->create();

    $this->actingAs($agent)
        ->patch(route('tickets.update', ['current_organization' => $organization, 'ticket' => $ticket]), [
            'client_id' => $client->id,
            'status' => TicketStatus::Resolved->value,
        ])
        ->assertRedirect();

    expect($ticket->fresh())
        ->client_id->toBe($client->id)
        ->status->toBe(TicketStatus::Resolved);
});

test('a ticket cannot be assigned to another organization\'s client', function () {
    [$organization, $agent] = ticketDesk();
    $ticket = Ticket::factory()->for($organization)->create();

    $this->actingAs($agent)
        ->patch(route('tickets.update', ['current_organization' => $organization, 'ticket' => $ticket]), [
            'client_id' => Client::factory()->create()->id,
        ])
        ->assertSessionHasErrors('client_id');

    expect($ticket->fresh()->client_id)->toBeNull();
});

test('an agent opens a ticket for a customer who phoned and emails them the first message', function () {
    Notification::fake();
    [$organization, $agent] = ticketDesk();

    $this->actingAs($agent)
        ->post(route('tickets.store', ['current_organization' => $organization]), [
            'requester_name' => 'Jane Doe',
            'requester_email' => 'Jane@Acme.test',
            'subject' => 'Called about exports',
            'body' => '<p>As discussed on the phone, we are looking into it.</p>',
            'send' => true,
        ])
        ->assertRedirect();

    $ticket = $organization->tickets()->sole();

    expect($ticket)
        ->requester_email->toBe('jane@acme.test')
        ->created_by->toBe($agent->id)
        ->status->toBe(TicketStatus::AwaitingCustomer);
    Notification::assertSentOnDemand(TicketReply::class);
});

test('a ticket opened without sending keeps its first message as an internal note', function () {
    Notification::fake();
    [$organization, $agent] = ticketDesk();

    $this->actingAs($agent)
        ->post(route('tickets.store', ['current_organization' => $organization]), [
            'requester_email' => 'jane@acme.test',
            'subject' => 'Called about exports',
            'requester_name' => 'Jane Doe',
            'body' => 'Wants a callback Monday.',
            'send' => false,
        ])
        ->assertRedirect();

    expect($organization->tickets()->sole()->comments()->sole()->is_internal)->toBeTrue();
    Notification::assertNothingSent();
});

test('opening a ticket needs a customer, a subject and a message', function () {
    [$organization, $agent] = ticketDesk();

    $this->actingAs($agent)
        ->post(route('tickets.store', ['current_organization' => $organization]), [])
        ->assertSessionHasErrors([
            'requester_name' => 'Pick a contact or enter the customer\'s name.',
            'requester_email' => 'Pick a contact or enter the customer\'s email.',
            'subject',
            'body',
        ]);
});

test('a ticket for an existing contact takes their email and client', function () {
    Notification::fake();
    [$organization, $agent] = ticketDesk();
    $contact = ClientContact::factory()->for(Client::factory()->for($organization))->create(['name' => 'Jane Doe', 'email' => 'jane@acme.test']);

    $this->actingAs($agent)
        ->post(route('tickets.store', ['current_organization' => $organization]), [
            'client_contact_id' => $contact->id,
            'subject' => 'Called about exports',
            'body' => 'Looking into it.',
        ])
        ->assertRedirect();

    expect($organization->tickets()->sole())
        ->client_contact_id->toBe($contact->id)
        ->client_id->toBe($contact->client_id)
        ->requester_name->toBe('Jane Doe')
        ->requester_email->toBe('jane@acme.test');
});

test('a new customer with a client is saved as that client\'s contact', function () {
    Notification::fake();
    [$organization, $agent] = ticketDesk();
    $client = Client::factory()->for($organization)->create();

    $this->actingAs($agent)
        ->post(route('tickets.store', ['current_organization' => $organization]), [
            'requester_name' => 'Bob Roe',
            'requester_email' => 'bob@acme.test',
            'client_id' => $client->id,
            'subject' => 'Called about exports',
            'body' => 'Looking into it.',
        ])
        ->assertRedirect();

    $contact = $client->contacts()->sole();

    expect($contact->name)->toBe('Bob Roe')
        ->and($organization->tickets()->sole()->client_contact_id)->toBe($contact->id);
});

test('a new customer without a client is not saved as a contact', function () {
    Notification::fake();
    [$organization, $agent] = ticketDesk();

    $this->actingAs($agent)
        ->post(route('tickets.store', ['current_organization' => $organization]), [
            'requester_name' => 'Bob Roe',
            'requester_email' => 'bob@example.test',
            'subject' => 'Called about exports',
            'body' => 'Looking into it.',
        ])
        ->assertRedirect();

    expect(ClientContact::count())->toBe(0)
        ->and($organization->tickets()->sole()->client_contact_id)->toBeNull();
});

test('a new customer whose email is already a contact is sent back to pick them', function () {
    [$organization, $agent] = ticketDesk();
    $client = Client::factory()->for($organization)->create();
    ClientContact::factory()->for($client)->create(['email' => 'bob@acme.test']);

    $this->actingAs($agent)
        ->post(route('tickets.store', ['current_organization' => $organization]), [
            'requester_name' => 'Bob Roe',
            'requester_email' => 'bob@acme.test',
            'client_id' => $client->id,
            'subject' => 'Called about exports',
            'body' => 'Looking into it.',
        ])
        ->assertSessionHasErrors(['requester_email' => 'That email already belongs to a contact. Pick them from the list.']);

    expect($organization->tickets()->count())->toBe(0);
});

test('ticket managers delete a ticket, but members cannot', function () {
    [$organization, $agent] = ticketDesk();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $ticket = Ticket::factory()->for($organization)->create();

    $this->actingAs($member)
        ->delete(route('tickets.destroy', ['current_organization' => $organization, 'ticket' => $ticket]))
        ->assertForbidden();

    $this->actingAs($agent)
        ->delete(route('tickets.destroy', ['current_organization' => $organization, 'ticket' => $ticket]))
        ->assertRedirect(route('tickets.index', ['current_organization' => $organization]));

    $this->assertModelMissing($ticket);
});

test('deleting a ticket removes its messages and attachment files but keeps its issue', function () {
    Storage::fake();
    [$organization, $agent] = ticketDesk();
    $issue = Issue::factory()->create();
    $ticket = Ticket::factory()->for($organization)->create(['issue_id' => $issue->id]);
    $comment = Comment::factory()->for($ticket, 'commentable')->create();
    $message = EmailMessage::factory()->for($comment)->create();
    $attachment = EmailAttachment::factory()->for($message)->create();
    Storage::put($attachment->path, 'image');

    $this->actingAs($agent)
        ->delete(route('tickets.destroy', ['current_organization' => $organization, 'ticket' => $ticket]))
        ->assertRedirect();

    $this->assertModelMissing($comment);
    $this->assertModelMissing($message);
    $this->assertModelMissing($attachment);
    Storage::assertMissing($attachment->path);
    $this->assertModelExists($issue);
});

test('every view counts the tickets it would show', function () {
    [$organization, $agent] = ticketDesk();
    $client = Client::factory()->for($organization)->create();
    Ticket::factory()->for($organization)->create();
    Ticket::factory()->forClient($client)->create(['reporter_id' => $agent->id, 'issue_resolved_at' => now()]);
    Ticket::factory()->forClient($client)->create(['status' => TicketStatus::Closed, 'issue_resolved_at' => now()]);

    $this->actingAs($agent)
        ->get(route('tickets.index', ['current_organization' => $organization]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts', ['inbox' => 2, 'unassigned' => 1, 'ready' => 1, 'mine' => 1]));
});

test('ticket managers delete several tickets at once, announcing it once', function () {
    [$organization, $agent] = ticketDesk();
    [$first, $second, $kept] = Ticket::factory()->for($organization)->count(3)->create();
    $elsewhere = Ticket::factory()->create();

    Event::fake([TicketsChanged::class]);

    $this->actingAs($agent)
        ->delete(route('tickets.destroy-many', ['current_organization' => $organization, 'ids' => [$first->id, $second->id, $elsewhere->id]]))
        ->assertRedirect();

    $this->assertModelMissing($first);
    $this->assertModelMissing($second);
    $this->assertModelExists($kept);
    $this->assertModelExists($elsewhere);
    Event::assertDispatchedTimes(TicketsChanged::class, 1);
});

test('members without ticket management cannot delete tickets in bulk', function () {
    [$organization] = ticketDesk();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $ticket = Ticket::factory()->for($organization)->create();

    $this->actingAs($member)
        ->delete(route('tickets.destroy-many', ['current_organization' => $organization, 'ids' => [$ticket->id]]))
        ->assertForbidden();

    $this->assertModelExists($ticket);
});

test('an agent assigns a ticket to the organization itself, filing it out of unassigned', function () {
    [$organization, $agent] = ticketDesk();
    $client = Client::factory()->for($organization)->create();
    $ticket = Ticket::factory()->forClient($client)->create();
    Project::factory()->for($organization)->create(['client_id' => null, 'name' => 'Internal tool']);

    $this->actingAs($agent)
        ->patch(route('tickets.update', ['current_organization' => $organization, 'ticket' => $ticket]), ['internal' => true])
        ->assertRedirect();

    expect($ticket->fresh())->is_internal->toBeTrue()->client_id->toBeNull();

    $this->actingAs($agent)
        ->get(route('tickets.index', ['current_organization' => $organization, 'client' => 'internal']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.isInternal', true)
            ->where('counts.unassigned', 0));

    $this->actingAs($agent)
        ->get(route('tickets.show', ['current_organization' => $organization, 'ticket' => $ticket]))
        ->assertInertia(fn (Assert $page) => $page->where('projects.0.name', 'Internal tool'));

    $this->actingAs($agent)
        ->patch(route('tickets.update', ['current_organization' => $organization, 'ticket' => $ticket]), ['client_id' => $client->id])
        ->assertRedirect();

    expect($ticket->fresh())->is_internal->toBeFalse()->client_id->toBe($client->id);
});
