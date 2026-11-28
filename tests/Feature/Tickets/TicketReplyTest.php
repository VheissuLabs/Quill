<?php

use App\Enums\EmailDeliveryStatus;
use App\Enums\TicketStatus;
use App\Models\Comment;
use App\Models\EmailMessage;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReply;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;

function replyingAgent(): array
{
    $organization = Organization::factory()->create();
    config([
        'services.resend.support_organization' => $organization->slug,
        'services.resend.support_address' => 'support@vheissulabs.com',
    ]);
    $agent = User::factory()->create(['name' => 'Karl']);
    $organization->addMember($agent, 'Admin');

    return [$organization, $agent];
}

test('a reply is emailed to the requester in their existing thread', function () {
    [$organization, $agent] = replyingAgent();
    $ticket = Ticket::factory()->for($organization)->create(['subject' => 'Export broken', 'requester_email' => 'jane@acme.test']);
    EmailMessage::factory()
        ->for(Comment::factory()->for($ticket, 'commentable'))
        ->create(['message_id' => 'first@mail.acme.test']);

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', ['current_organization' => $organization, 'ticket' => $ticket]), [
            'body' => '<p>Fixed, try again.</p>',
        ])
        ->assertRedirect();

    $sent = collect(app('mailer')->getSymfonyTransport()->messages())->sole()->getOriginalMessage();
    $reply = $ticket->comments()->latest('id')->first()->emailMessage;

    expect($sent)->toBeInstanceOf(Email::class)
        ->and($sent->getTo()[0]->getAddress())->toBe('jane@acme.test')
        ->and($sent->getReplyTo()[0]->getAddress())->toBe("support+t{$ticket->id}@vheissulabs.com")
        ->and($sent->getSubject())->toBe("Re: Export broken [T-{$ticket->number}]")
        ->and($sent->getHeaders()->get('Message-ID')->getBodyAsString())->toBe("<{$reply->message_id}>")
        ->and($sent->getHeaders()->get('In-Reply-To')->getBodyAsString())->toBe('<first@mail.acme.test>')
        ->and($sent->getHeaders()->get('References')->getBodyAsString())->toBe('<first@mail.acme.test>');
    expect($reply->delivery_status)->toBe(EmailDeliveryStatus::Sent)
        ->and($ticket->fresh()->status)->toBe(TicketStatus::AwaitingCustomer);
});

test('with an inbound address set, replies come from the support address and are answered at the inbound one', function () {
    [$organization, $agent] = replyingAgent();
    config(['services.resend.support_inbound_address' => 'support@in.vheissulabs.com']);
    $ticket = Ticket::factory()->for($organization)->create(['requester_email' => 'jane@acme.test']);

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', ['current_organization' => $organization, 'ticket' => $ticket]), [
            'body' => '<p>Fixed, try again.</p>',
        ])
        ->assertRedirect();

    $sent = collect(app('mailer')->getSymfonyTransport()->messages())->sole()->getOriginalMessage();

    expect($sent)->toBeInstanceOf(Email::class)
        ->and($sent->getFrom()[0]->getAddress())->toBe('support@vheissulabs.com')
        ->and($sent->getReplyTo()[0]->getAddress())->toBe("support+t{$ticket->id}@in.vheissulabs.com");
});

test('an internal note is never emailed', function () {
    Notification::fake();
    [$organization, $agent] = replyingAgent();
    $ticket = Ticket::factory()->for($organization)->create();

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', ['current_organization' => $organization, 'ticket' => $ticket]), [
            'body' => 'Customer is on the old plan.',
            'internal' => true,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
    expect($ticket->comments()->sole())
        ->is_internal->toBeTrue()
        ->emailMessage->toBeNull();
    expect($ticket->fresh()->status)->toBe(TicketStatus::Open);
});

test('a reply that could not be delivered is marked failed and can be sent again', function () {
    [$organization, $agent] = replyingAgent();
    $ticket = Ticket::factory()->for($organization)->create();
    $comment = Comment::factory()->for($ticket, 'commentable')->create(['user_id' => $agent->id]);
    $message = EmailMessage::factory()->for($comment)->outbound()->create();

    (new TicketReply($comment))->failed(new RuntimeException('Resend is down'));

    expect($message->fresh()->delivery_status)->toBe(EmailDeliveryStatus::Failed);

    Notification::fake();

    $this->actingAs($agent)
        ->post(route('tickets.replies.delivery.store', ['current_organization' => $organization, 'ticket' => $ticket, 'comment' => $comment]))
        ->assertRedirect();

    Notification::assertSentOnDemand(TicketReply::class);
});

test('a reply that was delivered cannot be sent twice', function () {
    Notification::fake();
    [$organization, $agent] = replyingAgent();
    $ticket = Ticket::factory()->for($organization)->create();
    $comment = Comment::factory()->for($ticket, 'commentable')->create();
    EmailMessage::factory()->for($comment)->outbound()->create(['delivery_status' => EmailDeliveryStatus::Sent]);

    $this->actingAs($agent)
        ->post(route('tickets.replies.delivery.store', ['current_organization' => $organization, 'ticket' => $ticket, 'comment' => $comment]))
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('members without ticket management cannot reply', function () {
    Notification::fake();
    [$organization] = replyingAgent();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $ticket = Ticket::factory()->for($organization)->create();

    $this->actingAs($member)
        ->post(route('tickets.replies.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['body' => 'Hi'])
        ->assertForbidden();

    Notification::assertNothingSent();
});

test('renaming a ticket keeps replies in the customer\'s original email thread', function () {
    [$organization, $agent] = replyingAgent();
    $ticket = Ticket::factory()->for($organization)->create(['subject' => 'help!!!', 'requester_email' => 'jane@acme.test']);

    $this->actingAs($agent)
        ->patch(route('tickets.update', ['current_organization' => $organization, 'ticket' => $ticket]), ['subject' => 'Export button does nothing'])
        ->assertRedirect();

    $this->actingAs($agent)
        ->post(route('tickets.replies.store', ['current_organization' => $organization, 'ticket' => $ticket]), ['body' => '<p>On it.</p>'])
        ->assertRedirect();

    $sent = collect(app('mailer')->getSymfonyTransport()->messages())->sole()->getOriginalMessage();

    expect($ticket->fresh()->subject)->toBe('Export button does nothing')
        ->and($sent)->toBeInstanceOf(Email::class)
        ->and($sent->getSubject())->toBe("Re: help!!! [T-{$ticket->number}]");
});

test('a ticket subject cannot be renamed to nothing', function () {
    [$organization, $agent] = replyingAgent();
    $ticket = Ticket::factory()->for($organization)->create(['subject' => 'Export broken']);

    $this->actingAs($agent)
        ->patch(route('tickets.update', ['current_organization' => $organization, 'ticket' => $ticket]), ['subject' => ''])
        ->assertSessionHasErrors('subject');

    expect($ticket->fresh()->subject)->toBe('Export broken');
});
