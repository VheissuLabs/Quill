<?php

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvoiceSent;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function sendableInvoice(bool $contact = true, bool $lines = true, string $state = 'create'): array
{
    $organization = Organization::factory()->create(['name' => 'Acme Studio']);
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');
    $client = Client::factory()->for($organization)->create();
    $recipient = ClientContact::factory()->for($client)->create(['email' => 'pat@client.test']);

    $factory = Invoice::factory()->for($organization)->for($client)->state([
        'contact_id' => $contact ? $recipient->id : null,
        'cc_emails' => ['boss@client.test', 'ap@client.test'],
    ]);
    $invoice = $state === 'create' ? $factory->create() : $factory->{$state}()->create();

    if ($lines) {
        $invoice->lines()->create(['description' => 'Development', 'quantity' => '10', 'unit' => LineUnit::Hours, 'rate' => '100.00']);
    }

    return [$invoice->fresh(), $admin];
}

test('sending emails the contact with the cc list and marks it sent', function () {
    Notification::fake();
    [$invoice, $admin] = sendableInvoice();

    $this->actingAs($admin)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertRedirect();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Sent)->sent_at->not->toBeNull();

    Notification::assertSentOnDemand(InvoiceSent::class, function (InvoiceSent $notification, array $channels, AnonymousNotifiable $notifiable) use ($invoice) {
        $mail = $notification->toMail($notifiable);

        return array_keys($notifiable->routes['mail']) === ['pat@client.test']
            && collect($mail->cc)->pluck(0)->all() === ['boss@client.test', 'ap@client.test']
            && $mail->subject === "Invoice {$invoice->number} from Acme Studio"
            && $mail->from[1] === 'Acme Studio'
            && $notification->afterCommit === true;
    });
});

test('an unsent invoice with a deposit is sent and keeps its partial status', function () {
    Notification::fake();
    [$invoice, $admin] = sendableInvoice();
    $invoice->payments()->create(['amount' => '100.00', 'paid_on' => '2026-11-15', 'method' => 'check']);

    $this->actingAs($admin)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertRedirect();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Partial)->sent_at->not->toBeNull();
});

test('an invoice with no contact or no lines cannot be sent', function () {
    [$noContact, $admin] = sendableInvoice(contact: false);
    [$noLines, $other] = sendableInvoice(lines: false);

    $this->actingAs($admin)->post(route('invoices.send.store', [$noContact->organization, $noContact]))->assertForbidden();
    $this->actingAs($other)->post(route('invoices.send.store', [$noLines->organization, $noLines]))->assertForbidden();
});

test('an already sent or void invoice cannot be sent again', function () {
    [$sent, $admin] = sendableInvoice(state: 'sent');
    [$void, $other] = sendableInvoice(state: 'void');

    $this->actingAs($admin)->post(route('invoices.send.store', [$sent->organization, $sent]))->assertForbidden();
    $this->actingAs($other)->post(route('invoices.send.store', [$void->organization, $void]))->assertForbidden();
});

test('members without invoice permission cannot send', function () {
    [$invoice] = sendableInvoice();
    $member = User::factory()->create();
    $invoice->organization->addMember($member, 'Member');

    $this->actingAs($member)->post(route('invoices.send.store', [$invoice->organization, $invoice]))->assertForbidden();
});
