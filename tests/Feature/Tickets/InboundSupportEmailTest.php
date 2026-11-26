<?php

use App\Enums\EmailDirection;
use App\Enums\TicketStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientDomain;
use App\Models\Comment;
use App\Models\EmailMessage;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReceived;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

function inboxOrganization(): Organization
{
    $organization = Organization::factory()->create();

    config([
        'services.resend.support_organization' => $organization->slug,
        'services.resend.support_address' => 'support@vheissulabs.com',
        'services.resend.webhook_secret' => 'whsec_'.base64_encode('a-test-signing-secret'),
    ]);

    return $organization;
}

/**
 * @param  array<string, mixed>  $payload
 */
function postResendWebhook(array $payload, ?int $timestamp = null, string $secret = 'a-test-signing-secret'): TestResponse
{
    $body = (string) json_encode($payload);
    $timestamp ??= now()->getTimestamp();
    $signature = base64_encode(hash_hmac('sha256', "msg_1.{$timestamp}.{$body}", $secret, true));

    return test()->call('POST', route('webhooks.resend'), [], [], [], [
        'HTTP_SVIX_ID' => 'msg_1',
        'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        'HTTP_SVIX_SIGNATURE' => "v1,{$signature}",
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeReceivedEmail(string $id, array $overrides = []): void
{
    Http::preventStrayRequests();
    Http::fake([
        "api.resend.com/emails/receiving/{$id}" => Http::response(array_replace_recursive([
            'object' => 'email',
            'id' => $id,
            'to' => ['support@vheissulabs.com'],
            'from' => 'jane@acme.test',
            'subject' => 'The export button does nothing',
            'html' => '<p>Clicking export does nothing.</p>',
            'text' => "Clicking export does nothing.\n\nOn Tue, Oct 1, 2026 at 9:00 AM Support <support@vheissulabs.com> wrote:\n> Earlier message",
            'headers' => ['from' => 'Jane Doe <jane@acme.test>'],
            'cc' => [],
            'message_id' => "<{$id}@mail.acme.test>",
            'attachments' => [],
        ], $overrides)),
    ]);
}

function deliverInboundEmail(string $id): void
{
    postResendWebhook(['type' => 'email.received', 'data' => ['email_id' => $id]])->assertNoContent();
}

test('a new email opens a ticket for the client that owns the sender domain', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $client = Client::factory()->for($organization)->create();
    ClientDomain::factory()->for($client)->create(['domain' => 'acme.test']);
    fakeReceivedEmail('email-1');

    deliverInboundEmail('email-1');

    $ticket = $organization->tickets()->sole();

    expect($ticket)
        ->client_id->toBe($client->id)
        ->subject->toBe('The export button does nothing')
        ->requester_email->toBe('jane@acme.test')
        ->requester_name->toBe('Jane Doe')
        ->status->toBe(TicketStatus::Open)
        ->number->toBe(1);
    expect($ticket->comments()->sole())
        ->body->toBe('<p>Clicking export does nothing.</p>')
        ->emailMessage->direction->toBe(EmailDirection::Inbound)
        ->emailMessage->message_id->toBe('email-1@mail.acme.test');
});

test('a matching domain records the sender as a contact of that client', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $client = Client::factory()->for($organization)->create();
    ClientDomain::factory()->for($client)->create(['domain' => 'acme.test']);
    fakeReceivedEmail('email-1');

    deliverInboundEmail('email-1');

    $contact = $client->contacts()->sole();

    expect($contact)
        ->name->toBe('Jane Doe')
        ->email->toBe('jane@acme.test');
    expect($organization->tickets()->sole()->client_contact_id)->toBe($contact->id);
});

test('a known contact is filed under their client even from a free mail address', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $contact = ClientContact::factory()->for(Client::factory()->for($organization))->create(['email' => 'jane@gmail.com']);
    fakeReceivedEmail('email-1', ['from' => 'jane@gmail.com']);

    deliverInboundEmail('email-1');

    expect($organization->tickets()->sole())
        ->client_id->toBe($contact->client_id)
        ->client_contact_id->toBe($contact->id);
});

test('a contact of a deleted client is no longer filed under that client', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $contact = ClientContact::factory()->for(Client::factory()->for($organization))->create(['email' => 'jane@gmail.com']);
    $contact->client->deleteKeepingRecords();
    fakeReceivedEmail('email-1', ['from' => 'jane@gmail.com']);

    deliverInboundEmail('email-1');

    expect($organization->tickets()->sole())
        ->client_id->toBeNull()
        ->client_contact_id->toBeNull();
});

test('mail from a free mail domain waits unassigned', function () {
    Notification::fake();
    $organization = inboxOrganization();
    fakeReceivedEmail('email-1', ['from' => 'jane@gmail.com']);

    deliverInboundEmail('email-1');

    expect($organization->tickets()->sole()->client_id)->toBeNull()
        ->and(ClientContact::count())->toBe(0);
});

test('a sender who has an account is linked to it', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $user = User::factory()->create(['email' => 'jane@acme.test']);
    fakeReceivedEmail('email-1');

    deliverInboundEmail('email-1');

    expect($organization->tickets()->sole()->requester_user_id)->toBe($user->id)
        ->and(Comment::sole()->user_id)->toBe($user->id);
});

test('a reply to the ticket address joins that ticket and reopens it', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $ticket = Ticket::factory()->for($organization)->create(['requester_email' => 'jane@acme.test', 'status' => TicketStatus::Resolved]);
    fakeReceivedEmail('email-2', ['to' => ["support+t{$ticket->id}@vheissulabs.com"]]);

    deliverInboundEmail('email-2');

    expect($organization->tickets()->count())->toBe(1)
        ->and($ticket->comments()->count())->toBe(1)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

test('with an inbound address set, a reply to the ticket address on it joins that ticket', function () {
    Notification::fake();
    $organization = inboxOrganization();
    config(['services.resend.support_inbound_address' => 'support@in.vheissulabs.com']);
    $ticket = Ticket::factory()->for($organization)->create(['requester_email' => 'jane@acme.test']);
    fakeReceivedEmail('email-2', ['to' => ["support+t{$ticket->id}@in.vheissulabs.com"]]);

    deliverInboundEmail('email-2');

    expect($organization->tickets()->count())->toBe(1)
        ->and($ticket->comments()->count())->toBe(1);
});

test('mail sent from the inbound address itself is dropped', function () {
    Notification::fake();
    $organization = inboxOrganization();
    config(['services.resend.support_inbound_address' => 'support@in.vheissulabs.com']);
    fakeReceivedEmail('email-1', ['from' => 'support@in.vheissulabs.com', 'headers' => ['from' => 'Support <support@in.vheissulabs.com>']]);

    deliverInboundEmail('email-1');

    expect($organization->tickets()->count())->toBe(0);
});

test('a reply that quotes an earlier message joins its ticket', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $ticket = Ticket::factory()->for($organization)->create();
    EmailMessage::factory()
        ->for(Comment::factory()->for($ticket, 'commentable'))
        ->create(['message_id' => 'ticket-1-1.abc@vheissulabs.com']);
    fakeReceivedEmail('email-2', ['headers' => ['in-reply-to' => '<ticket-1-1.abc@vheissulabs.com>']]);

    deliverInboundEmail('email-2');

    expect($organization->tickets()->count())->toBe(1)
        ->and($ticket->comments()->count())->toBe(2);
});

test('a ticket address used by someone other than the requester opens a new ticket', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $ticket = Ticket::factory()->for($organization)->create(['requester_email' => 'jane@acme.test']);
    fakeReceivedEmail('email-2', ['from' => 'mallory@evil.test', 'to' => ["support+t{$ticket->id}@vheissulabs.com"]]);

    deliverInboundEmail('email-2');

    expect($ticket->comments()->count())->toBe(0)
        ->and($organization->tickets()->count())->toBe(2);
});

test('the same email delivered twice is stored once', function () {
    Notification::fake();
    $organization = inboxOrganization();
    fakeReceivedEmail('email-1');

    deliverInboundEmail('email-1');
    deliverInboundEmail('email-1');

    expect($organization->tickets()->count())->toBe(1)
        ->and(Comment::count())->toBe(1);
});

test('automatic replies are dropped', function () {
    Notification::fake();
    $organization = inboxOrganization();
    fakeReceivedEmail('email-1', ['headers' => ['auto-submitted' => 'auto-replied']]);

    deliverInboundEmail('email-1');

    expect($organization->tickets()->count())->toBe(0);
});

test('attachments are copied out of resend', function () {
    Notification::fake();
    Storage::fake();
    $organization = inboxOrganization();
    fakeReceivedEmail('email-1', ['attachments' => [['id' => 'att-1', 'filename' => 'Screen Shot.png', 'content_type' => 'image/png', 'size' => 5]]]);
    Http::fake([
        'api.resend.com/emails/receiving/email-1/attachments/att-1' => Http::response(['download_url' => 'https://inbound-cdn.resend.com/att-1']),
        'inbound-cdn.resend.com/att-1' => Http::response('bytes'),
    ]);

    deliverInboundEmail('email-1');

    $attachment = EmailMessage::sole()->attachments()->sole();

    expect($attachment->filename)->toBe('Screen Shot.png');
    Storage::assertExists($attachment->path);
});

test('the reporter hears about a customer reply, or every ticket manager when nobody reported it', function () {
    Notification::fake();
    $organization = inboxOrganization();
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $organization->addMember($manager, 'Admin');
    $organization->addMember($member, 'Member');
    fakeReceivedEmail('email-1');

    deliverInboundEmail('email-1');

    Notification::assertSentTo($manager, TicketReceived::class);
    Notification::assertNotSentTo($member, TicketReceived::class);
});

test('a webhook with a bad signature is refused', function () {
    inboxOrganization();
    postResendWebhook(['type' => 'email.received', 'data' => ['email_id' => 'email-1']], secret: 'someone-else')->assertUnauthorized();

    expect(Ticket::count())->toBe(0);
});

test('a webhook signed too long ago is refused', function () {
    inboxOrganization();
    postResendWebhook(['type' => 'email.received', 'data' => ['email_id' => 'email-1']], timestamp: now()->subMinutes(10)->getTimestamp())->assertUnauthorized();
});

test('webhook events other than received mail are acknowledged and ignored', function () {
    inboxOrganization();
    Http::preventStrayRequests();
    postResendWebhook(['type' => 'email.delivered', 'data' => ['email_id' => 'email-1']])->assertNoContent();

    expect(Ticket::count())->toBe(0);
});
