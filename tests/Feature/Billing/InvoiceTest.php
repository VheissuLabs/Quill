<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Carbon\CarbonImmutable;

function invoiceManager(): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    return [$organization, $admin, Client::factory()->for($organization)->create()];
}

function invoiceRoute(string $name, Organization $organization, ?Invoice $invoice = null): string
{
    return route($name, array_filter(['current_organization' => $organization, 'invoice' => $invoice]));
}

test('a manager sees the workspace\'s invoices only', function () {
    [$organization, $admin, $client] = invoiceManager();
    $mine = Invoice::factory()->for($organization)->for($client)->create();
    Invoice::factory()->create();

    $this->actingAs($admin)
        ->get(invoiceRoute('invoices.index', $organization))
        ->assertInertia(fn ($page) => $page
            ->component('invoices/Index')
            ->has('invoices', 1)
            ->where('invoices.0.id', $mine->id)
            ->where('invoices.0.number', $mine->number)
            ->where('invoices.0.clientSlug', $client->slug));
});

test('the index filters by status and client', function () {
    [$organization, $admin, $client] = invoiceManager();
    $other = Client::factory()->for($organization)->create();
    $draft = Invoice::factory()->for($organization)->for($client)->create();
    Invoice::factory()->sent()->for($organization)->for($client)->create();
    Invoice::factory()->for($organization)->for($other)->create();

    $this->actingAs($admin)
        ->get(invoiceRoute('invoices.index', $organization).'?status=draft&client='.$client->id)
        ->assertInertia(fn ($page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.id', $draft->id));
});

test('the new invoice form offers only billable projects with who they bill to', function () {
    [$organization, $admin, $client] = invoiceManager();
    $subcontract = Client::factory()->for($organization)->create(['billed_through_client_id' => $client->id]);
    $own = Project::factory()->for($organization)->create(['client_id' => $client->id, 'name' => 'A']);
    $billedThrough = Project::factory()->for($organization)->create(['client_id' => $subcontract->id, 'name' => 'B']);
    Project::factory()->for($organization)->create(['client_id' => null, 'name' => 'C']);

    $this->actingAs($admin)
        ->get(invoiceRoute('invoices.index', $organization))
        ->assertInertia(fn ($page) => $page
            ->has('projects', 2)
            ->where('projects.0', ['id' => $own->id, 'name' => 'A', 'billToClientId' => $client->id])
            ->where('projects.1', ['id' => $billedThrough->id, 'name' => 'B', 'billToClientId' => $client->id]));
});

test('a manager creates an invoice for a client', function () {
    [$organization, $admin, $client] = invoiceManager();

    $response = $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id]);

    $invoice = $organization->invoices()->sole();

    $response->assertRedirect(invoiceRoute('invoices.show', $organization, $invoice));
    expect($invoice)->status->toBe(InvoiceStatus::Draft)->client_id->toBe($client->id);
});

test('starting from a project bills its billed-through client', function () {
    [$organization, $admin, $payer] = invoiceManager();
    $kms = Client::factory()->for($organization)->create(['billed_through_client_id' => $payer->id]);
    $project = Project::factory()->for($organization)->create(['client_id' => $kms->id]);

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['project_id' => $project->id])
        ->assertSessionHasNoErrors();

    expect($organization->invoices()->sole())->client_id->toBe($payer->id)->project_id->toBe($project->id);
});

test('a project without a client needs a client chosen', function () {
    [$organization, $admin] = invoiceManager();
    $project = Project::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['project_id' => $project->id])
        ->assertSessionHasErrors('client_id');
});

test('project must be billable to the invoice\'s client', function () {
    [$organization, $admin, $client] = invoiceManager();
    $stranger = Project::factory()->for($organization)->create([
        'client_id' => Client::factory()->for($organization),
    ]);

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id, 'project_id' => $stranger->id])
        ->assertSessionHasErrors('project_id');
});

test('contact must belong to the invoice\'s client', function () {
    [$organization, $admin, $client] = invoiceManager();
    $stranger = ClientContact::factory()->for(Client::factory()->for($organization))->create();
    $contact = ClientContact::factory()->for($client)->create();

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id, 'contact_id' => $stranger->id])
        ->assertSessionHasErrors('contact_id');

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id, 'contact_id' => $contact->id])
        ->assertSessionHasNoErrors();
});

test('cc emails must each be an email', function () {
    [$organization, $admin, $client] = invoiceManager();

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id, 'cc_emails' => ['ok@example.com', 'nope']])
        ->assertSessionHasErrors('cc_emails.1');
});

test('a draft\'s header and amounts update and totals are recalculated', function () {
    [$organization, $admin, $client] = invoiceManager();
    $invoice = Invoice::factory()->for($organization)->for($client)->create();
    $invoice->lines()->create(['description' => 'Dev', 'quantity' => '10', 'unit' => 'hours', 'rate' => '100.00']);

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['tax_rate' => '10'])
        ->assertSessionHasNoErrors();

    expect($invoice->fresh())->tax_amount->toBe('100.00')->total->toBe('1100.00');
});

test('money fields cannot change once sent', function () {
    [$organization, $admin, $client] = invoiceManager();
    $invoice = Invoice::factory()->sent()->for($organization)->for($client)->create();

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['tax_rate' => '10'])
        ->assertSessionHasErrors('tax_rate');

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['cc_emails' => ['finance@example.com']])
        ->assertSessionHasNoErrors();

    expect($invoice->fresh())->cc_emails->toBe(['finance@example.com']);
});

test('void invoices cannot be edited', function () {
    [$organization, $admin, $client] = invoiceManager();
    $invoice = Invoice::factory()->void()->for($organization)->for($client)->create();

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['cc_emails' => ['nope@example.com']])
        ->assertForbidden();
});

test('drafts can be deleted, sent invoices cannot', function () {
    [$organization, $admin, $client] = invoiceManager();
    $draft = Invoice::factory()->for($organization)->for($client)->create();
    $sent = Invoice::factory()->sent()->for($organization)->for($client)->create();

    $this->actingAs($admin)->delete(invoiceRoute('invoices.destroy', $organization, $sent))->assertForbidden();
    $this->actingAs($admin)->delete(invoiceRoute('invoices.destroy', $organization, $draft))->assertRedirect(invoiceRoute('invoices.index', $organization));

    expect(Invoice::find($draft->id))->toBeNull()->and(Invoice::find($sent->id))->not->toBeNull();
});

test('a draft with payments cannot be deleted', function () {
    [$organization, $admin, $client] = invoiceManager();
    $draft = Invoice::factory()->for($organization)->for($client)->create();
    InvoicePayment::factory()->for($draft)->create();

    $this->actingAs($admin)->delete(invoiceRoute('invoices.destroy', $organization, $draft))->assertForbidden();
});

test('the invoice page carries its resource and options', function () {
    [$organization, $admin, $client] = invoiceManager();
    $contact = ClientContact::factory()->for($client)->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->create();

    $this->actingAs($admin)
        ->get(invoiceRoute('invoices.show', $organization, $invoice))
        ->assertInertia(fn ($page) => $page
            ->component('invoices/Show')
            ->where('invoice.id', $invoice->id)
            ->where('invoice.isEditable', true)
            ->where('invoice.amountDue', '0.00')
            ->where('invoice.options.contacts.0.id', $contact->id)
            ->missing('invoice.publicUrl'));
});

test('invoices from another workspace are 404', function () {
    [$organization, $admin] = invoiceManager();
    $foreign = Invoice::factory()->create();

    $this->actingAs($admin)->get(invoiceRoute('invoices.show', $organization, $foreign))->assertNotFound();
    $this->actingAs($admin)->patch(invoiceRoute('invoices.update', $organization, $foreign), ['cc_emails' => ['x@example.com']])->assertNotFound();
    $this->actingAs($admin)->delete(invoiceRoute('invoices.destroy', $organization, $foreign))->assertNotFound();
});

test('members without invoice management get 403', function () {
    [$organization, , $client] = invoiceManager();
    $member = User::factory()->create();
    $organization->addMember($member, 'Member');
    $invoice = Invoice::factory()->for($organization)->for($client)->create();

    $this->actingAs($member)->get(invoiceRoute('invoices.index', $organization))->assertForbidden();
    $this->actingAs($member)->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id])->assertForbidden();
    $this->actingAs($member)->get(invoiceRoute('invoices.show', $organization, $invoice))->assertForbidden();
    $this->actingAs($member)->patch(invoiceRoute('invoices.update', $organization, $invoice), ['cc_emails' => ['x@example.com']])->assertForbidden();
    $this->actingAs($member)->delete(invoiceRoute('invoices.destroy', $organization, $invoice))->assertForbidden();
});

test('rejected money edits leave the stored amounts untouched', function (array $payload) {
    [$organization, $admin, $client] = invoiceManager();
    $invoice = Invoice::factory()->sent()->for($organization)->for($client)->create(['tax_rate' => '5.00']);

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), $payload)
        ->assertSessionHasErrors(array_key_first($payload));

    expect($invoice->fresh())
        ->tax_rate->toBe('5.00')
        ->discount_type->toBeNull()
        ->discount_value->toBeNull()
        ->deposit_type->toBeNull()
        ->deposit_value->toBeNull();
})->with([
    'tax' => [['tax_rate' => '10']],
    'discount type' => [['discount_type' => 'percentage', 'discount_value' => '10']],
    'discount value' => [['discount_value' => '10']],
    'deposit type' => [['deposit_type' => 'fixed', 'deposit_value' => '50']],
    'deposit value' => [['deposit_value' => '50']],
]);

test('money fields cannot change on a paid invoice that was never sent', function () {
    [$organization, $admin, $client] = invoiceManager();
    $invoice = Invoice::factory()->for($organization)->for($client)->create(['status' => InvoiceStatus::Paid, 'tax_rate' => '5.00']);

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['tax_rate' => '10', 'discount_type' => 'fixed', 'discount_value' => '5'])
        ->assertSessionHasErrors(['tax_rate', 'discount_type', 'discount_value']);

    expect($invoice->fresh())->tax_rate->toBe('5.00')->discount_type->toBeNull();
});

test('another workspace\'s client cannot be invoiced', function () {
    [$organization, $admin] = invoiceManager();
    $foreign = Client::factory()->create();

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $foreign->id])
        ->assertSessionHasErrors('client_id');

    expect($organization->invoices()->count())->toBe(0);
});

test('another workspace\'s project and sprint cannot be attached', function () {
    [$organization, $admin, $client] = invoiceManager();
    $foreignProject = Project::factory()->create(['client_id' => Client::factory()]);
    $foreignSprint = Sprint::factory()->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->create();

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id, 'project_id' => $foreignProject->id])
        ->assertSessionHasErrors('project_id');

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id, 'sprint_id' => $foreignSprint->id])
        ->assertSessionHasErrors('sprint_id');

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['project_id' => $foreignProject->id])
        ->assertSessionHasErrors('project_id');

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['sprint_id' => $foreignSprint->id])
        ->assertSessionHasErrors('sprint_id');

    expect($invoice->fresh())->project_id->toBeNull()->sprint_id->toBeNull();
});

test('a contact from another client cannot be set on update', function () {
    [$organization, $admin, $client] = invoiceManager();
    $stranger = ClientContact::factory()->for(Client::factory()->for($organization))->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->create();

    $this->actingAs($admin)
        ->patch(invoiceRoute('invoices.update', $organization, $invoice), ['contact_id' => $stranger->id])
        ->assertSessionHasErrors('contact_id');

    expect($invoice->fresh()->contact_id)->toBeNull();
});

test('an array of client ids is rejected instead of reaching the lookup', function () {
    [$organization, $admin, $client] = invoiceManager();

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => [$client->id]])
        ->assertSessionHasErrors('client_id');

    expect($organization->invoices()->count())->toBe(0);
});

test('a cycle draft carries when it sends', function () {
    [$organization, $admin, $client] = invoiceManager();
    $cycle = Invoice::factory()->for($organization)->for($client)
        ->cycle(CarbonImmutable::parse('2026-11-14'), CarbonImmutable::parse('2026-11-27'))
        ->create(['closes_at' => '2026-11-28 05:59:00']);
    $manual = Invoice::factory()->for($organization)->for($client)->create();

    $this->actingAs($admin)
        ->get(invoiceRoute('invoices.show', $organization, $cycle))
        ->assertInertia(fn ($page) => $page->where('invoice.closesAt', '2026-11-28T05:59:00+00:00'));

    $this->actingAs($admin)
        ->get(invoiceRoute('invoices.show', $organization, $manual))
        ->assertInertia(fn ($page) => $page->where('invoice.closesAt', null));
});

test('a cycle draft does not say it sends while billing cycles are off', function () {
    [$organization, $admin, $client] = invoiceManager();
    $organization->updateModuleSettings(['billing_enabled' => true, 'billing_invoices_enabled' => true, 'billing_billing_cycles_enabled' => false]);
    $cycle = Invoice::factory()->for($organization)->for($client)
        ->cycle(CarbonImmutable::parse('2026-11-14'), CarbonImmutable::parse('2026-11-27'))
        ->create(['closes_at' => '2026-11-28 05:59:00']);

    $this->actingAs($admin)
        ->get(invoiceRoute('invoices.show', $organization, $cycle))
        ->assertInertia(fn ($page) => $page->where('invoice.closesAt', null));
});

test('a new invoice takes the client\'s payment terms and CC', function () {
    [$organization, $admin, $client] = invoiceManager();
    $client->update(['payment_terms_days' => 7, 'billing_cc_emails' => ['ap@example.com']]);
    $this->travelTo('2026-11-16 10:00');

    $this->actingAs($admin)
        ->post(invoiceRoute('invoices.store', $organization), ['client_id' => $client->id])
        ->assertSessionHasNoErrors();

    expect($organization->invoices()->sole())
        ->due_date->toDateString()->toBe('2026-11-23')
        ->cc_emails->toBe(['ap@example.com']);
});
