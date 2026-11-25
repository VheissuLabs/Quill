<?php

use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Mcp\Servers\QuillServer;
use App\Mcp\Tools\Billing\AddInvoiceLineTool;
use App\Mcp\Tools\Billing\CreateInvoiceTool;
use App\Mcp\Tools\Billing\CreateWorkTypeTool;
use App\Mcp\Tools\Billing\DeleteInvoiceTool;
use App\Mcp\Tools\Billing\DeletePaymentTool;
use App\Mcp\Tools\Billing\DeleteWorkTypeTool;
use App\Mcp\Tools\Billing\DuplicateInvoiceTool;
use App\Mcp\Tools\Billing\ListWorkTypesTool;
use App\Mcp\Tools\Billing\RecordPaymentTool;
use App\Mcp\Tools\Billing\RemoveInvoiceLineTool;
use App\Mcp\Tools\Billing\ReorderInvoiceLinesTool;
use App\Mcp\Tools\Billing\SendInvoiceTool;
use App\Mcp\Tools\Billing\ShowBillingSettingsTool;
use App\Mcp\Tools\Billing\UpdateBillingSettingsTool;
use App\Mcp\Tools\Billing\UpdateInvoiceLineTool;
use App\Mcp\Tools\Billing\UpdateInvoiceTool;
use App\Mcp\Tools\Billing\UpdatePaymentTool;
use App\Mcp\Tools\Billing\UpdateWorkTypeTool;
use App\Mcp\Tools\Billing\VoidInvoiceTool;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use App\Notifications\InvoiceSent;
use Illuminate\Support\Facades\Notification;

function billingToolMember(string $role = 'Admin'): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->addMember($user, $role);
    $user->switchOrganization($organization);

    return [$user, $organization, Client::factory()->for($organization)->create(['billing_rate' => 100])];
}

function billingToolInvoice(Organization $organization, Client $client, string $state = 'create'): Invoice
{
    $factory = Invoice::factory()->for($organization)->for($client);

    return $state === 'create' ? $factory->create() : $factory->{$state}()->create();
}

test('it creates a draft invoice for a client', function () {
    [$user, $organization, $client] = billingToolMember();

    QuillServer::actingAs($user)->tool(CreateInvoiceTool::class, ['client_id' => $client->id, 'tax_rate' => 5])->assertOk();

    expect($client->invoices()->sole())
        ->status->toBe(InvoiceStatus::Draft)
        ->tax_rate->toBe('5.00')
        ->number->toStartWith('INV-');
});

test('creating an invoice for a project bills its client', function () {
    [$user, $organization, $client] = billingToolMember();
    $project = Project::factory()->for($organization)->create(['client_id' => $client->id]);

    QuillServer::actingAs($user)->tool(CreateInvoiceTool::class, ['project_id' => $project->id])->assertOk();

    expect($client->invoices()->sole()->project_id)->toBe($project->id);
});

test('members cannot create invoices', function () {
    [$user, , $client] = billingToolMember('Member');

    QuillServer::actingAs($user)->tool(CreateInvoiceTool::class, ['client_id' => $client->id])->assertHasErrors();

    expect(Invoice::query()->exists())->toBeFalse();
});

test('invoices cannot be created for another organization\'s client', function () {
    [$user] = billingToolMember();
    $foreign = Client::factory()->create();

    QuillServer::actingAs($user)->tool(CreateInvoiceTool::class, ['client_id' => $foreign->id])->assertHasErrors(['client id']);

    expect(Invoice::query()->exists())->toBeFalse();
});

test('it updates an invoice and recalculates it', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client);
    InvoiceLine::factory()->for($invoice)->create(['quantity' => '2.00', 'rate' => '100.00']);

    QuillServer::actingAs($user)->tool(UpdateInvoiceTool::class, ['invoice_id' => $invoice->id, 'tax_rate' => 10])->assertOk();

    expect($invoice->fresh())->tax_rate->toBe('10.00')->total->toBe('220.00');
});

test('a sent invoice\'s amounts cannot change', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client, 'sent');

    QuillServer::actingAs($user)->tool(UpdateInvoiceTool::class, ['invoice_id' => $invoice->id, 'tax_rate' => 10])->assertHasErrors(['This invoice has been sent']);
});

test('members cannot update invoices', function () {
    [$user, $organization, $client] = billingToolMember('Member');
    $invoice = billingToolInvoice($organization, $client);

    QuillServer::actingAs($user)->tool(UpdateInvoiceTool::class, ['invoice_id' => $invoice->id, 'tax_rate' => 10])->assertHasErrors();
});

test('it deletes a draft invoice but not a sent one', function () {
    [$user, $organization, $client] = billingToolMember();
    $draft = billingToolInvoice($organization, $client);
    $sent = billingToolInvoice($organization, $client, 'sent');

    QuillServer::actingAs($user)->tool(DeleteInvoiceTool::class, ['invoice_id' => $draft->id])->assertOk();
    QuillServer::actingAs($user)->tool(DeleteInvoiceTool::class, ['invoice_id' => $sent->id])->assertHasErrors();

    expect($draft->fresh())->toBeNull()
        ->and($sent->fresh())->not->toBeNull();
});

test('it sends an invoice to its contact', function () {
    Notification::fake();
    [$user, $organization, $client] = billingToolMember();
    $contact = ClientContact::factory()->for($client)->create(['email' => 'pat@client.test']);
    $invoice = Invoice::factory()->for($organization)->for($client)->create(['contact_id' => $contact->id]);
    InvoiceLine::factory()->for($invoice)->create();

    QuillServer::actingAs($user)->tool(SendInvoiceTool::class, ['invoice_id' => $invoice->id])->assertOk();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Sent)->sent_at->not->toBeNull();
    Notification::assertSentOnDemand(InvoiceSent::class);
});

test('an invoice without a contact cannot be sent', function () {
    Notification::fake();
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client);
    InvoiceLine::factory()->for($invoice)->create();

    QuillServer::actingAs($user)->tool(SendInvoiceTool::class, ['invoice_id' => $invoice->id])->assertHasErrors();

    expect($invoice->fresh()->sent_at)->toBeNull();
    Notification::assertNothingSent();
});

test('members cannot send invoices', function () {
    Notification::fake();
    [$user, $organization, $client] = billingToolMember('Member');
    $contact = ClientContact::factory()->for($client)->create();
    $invoice = Invoice::factory()->for($organization)->for($client)->create(['contact_id' => $contact->id]);
    InvoiceLine::factory()->for($invoice)->create();

    QuillServer::actingAs($user)->tool(SendInvoiceTool::class, ['invoice_id' => $invoice->id])->assertHasErrors();

    Notification::assertNothingSent();
});

test('it voids a sent invoice', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client, 'sent');

    QuillServer::actingAs($user)->tool(VoidInvoiceTool::class, ['invoice_id' => $invoice->id])->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

test('a draft invoice cannot be voided', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client);

    QuillServer::actingAs($user)->tool(VoidInvoiceTool::class, ['invoice_id' => $invoice->id])->assertHasErrors();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Draft);
});

test('it duplicates an invoice with its lines', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client, 'sent');
    InvoiceLine::factory()->for($invoice)->create(['description' => 'Design work']);

    QuillServer::actingAs($user)->tool(DuplicateInvoiceTool::class, ['invoice_id' => $invoice->id])->assertOk()->assertSee('Design work');

    $copy = $client->invoices()->whereKeyNot($invoice->id)->sole();
    expect($copy)->status->toBe(InvoiceStatus::Draft)
        ->and($copy->lines()->sole()->description)->toBe('Design work');
});

test('members cannot duplicate invoices', function () {
    [$user, $organization, $client] = billingToolMember('Member');
    $invoice = billingToolInvoice($organization, $client);

    QuillServer::actingAs($user)->tool(DuplicateInvoiceTool::class, ['invoice_id' => $invoice->id])->assertHasErrors();

    expect(Invoice::query()->count())->toBe(1);
});

test('it adds a line at the client rate titled after its issue', function () {
    [$user, $organization, $client] = billingToolMember();
    $issue = Issue::factory()->for(Project::factory()->for($organization)->state(['client_id' => $client->id]))->create(['title' => 'Build login']);
    $invoice = billingToolInvoice($organization, $client);

    QuillServer::actingAs($user)->tool(AddInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'issue_id' => $issue->id, 'quantity' => 1.5])->assertOk();

    expect($invoice->lines()->sole())
        ->description->toBe('Build login')
        ->rate->toBe('100.00')
        ->amount->toBe('150.00')
        ->and($invoice->fresh()->total)->toBe('150.00');
});

test('lines cannot be added to a sent invoice', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client, 'sent');

    QuillServer::actingAs($user)->tool(AddInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'description' => 'Extra', 'quantity' => 1])->assertHasErrors();

    expect($invoice->lines()->exists())->toBeFalse();
});

test('members cannot add invoice lines', function () {
    [$user, $organization, $client] = billingToolMember('Member');
    $invoice = billingToolInvoice($organization, $client);

    QuillServer::actingAs($user)->tool(AddInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'description' => 'Extra', 'quantity' => 1])->assertHasErrors();

    expect($invoice->lines()->exists())->toBeFalse();
});

test('it updates, reorders and removes invoice lines', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client);
    $first = InvoiceLine::factory()->for($invoice)->create(['sort_order' => 0]);
    $second = InvoiceLine::factory()->for($invoice)->create(['sort_order' => 1]);

    QuillServer::actingAs($user)->tool(UpdateInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'line_id' => $first->id, 'quantity' => 3, 'unit' => LineUnit::Units->value])->assertOk();

    expect($first->fresh())->quantity->toBe('3.00')->unit->toBe(LineUnit::Units)->amount->toBe('300.00');

    QuillServer::actingAs($user)->tool(ReorderInvoiceLinesTool::class, ['invoice_id' => $invoice->id, 'line_ids' => [$second->id, $first->id]])->assertOk();

    expect($invoice->lines()->pluck('id')->all())->toBe([$second->id, $first->id]);

    QuillServer::actingAs($user)->tool(RemoveInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'line_id' => $first->id])->assertOk();

    expect($invoice->lines()->pluck('id')->all())->toBe([$second->id]);
});

test('reordering needs every line of the invoice', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client);
    $line = InvoiceLine::factory()->for($invoice)->create();
    InvoiceLine::factory()->for($invoice)->create();

    QuillServer::actingAs($user)->tool(ReorderInvoiceLinesTool::class, ['invoice_id' => $invoice->id, 'line_ids' => [$line->id]])->assertHasErrors(['line ids']);
});

test('a line must belong to the named invoice', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client);
    $other = InvoiceLine::factory()->for(billingToolInvoice($organization, $client))->create();

    QuillServer::actingAs($user)->tool(RemoveInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'line_id' => $other->id])->assertHasErrors();

    expect($other->fresh())->not->toBeNull();
});

test('members cannot change invoice lines', function () {
    [$user, $organization, $client] = billingToolMember('Member');
    $invoice = billingToolInvoice($organization, $client);
    $line = InvoiceLine::factory()->for($invoice)->create();

    QuillServer::actingAs($user)->tool(UpdateInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'line_id' => $line->id, 'quantity' => 9])->assertHasErrors();
    QuillServer::actingAs($user)->tool(RemoveInvoiceLineTool::class, ['invoice_id' => $invoice->id, 'line_id' => $line->id])->assertHasErrors();

    expect($line->fresh())->not->toBeNull();
});

test('it records, corrects and deletes payments', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client, 'sent');
    InvoiceLine::factory()->for($invoice)->create(['quantity' => '2.00', 'rate' => '100.00']);

    QuillServer::actingAs($user)->tool(RecordPaymentTool::class, ['invoice_id' => $invoice->id, 'amount' => 50, 'paid_on' => '2026-11-20', 'method' => 'check'])->assertOk();

    $payment = $invoice->payments()->sole();
    expect($invoice->fresh())->status->toBe(InvoiceStatus::Partial)->amount_paid->toBe('50.00');

    QuillServer::actingAs($user)->tool(UpdatePaymentTool::class, ['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'amount' => 200])->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($payment->fresh()->method->value)->toBe('check');

    QuillServer::actingAs($user)->tool(DeletePaymentTool::class, ['invoice_id' => $invoice->id, 'payment_id' => $payment->id])->assertOk();

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Sent)->amount_paid->toBe('0.00');
});

test('payments use the web validation', function () {
    [$user, $organization, $client] = billingToolMember();
    $invoice = billingToolInvoice($organization, $client, 'sent');

    QuillServer::actingAs($user)->tool(RecordPaymentTool::class, ['invoice_id' => $invoice->id, 'amount' => 0, 'paid_on' => '2026-11-20', 'method' => 'barter'])
        ->assertHasErrors(['amount', 'method']);
});

test('members cannot manage payments', function () {
    [$user, $organization, $client] = billingToolMember('Member');
    $invoice = billingToolInvoice($organization, $client, 'sent');
    $payment = InvoicePayment::factory()->for($invoice)->create();

    QuillServer::actingAs($user)->tool(RecordPaymentTool::class, ['invoice_id' => $invoice->id, 'amount' => 50, 'paid_on' => '2026-11-20', 'method' => 'check'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeletePaymentTool::class, ['invoice_id' => $invoice->id, 'payment_id' => $payment->id])->assertHasErrors();

    expect($invoice->payments()->count())->toBe(1);
});

test('it shows and updates billing settings without exposing secrets', function () {
    [$user, $organization] = billingToolMember();
    $organization->billingSettings()->update(['square_access_token' => 'secret-token']);

    QuillServer::actingAs($user)->tool(ShowBillingSettingsTool::class)
        ->assertOk()
        ->assertSee('INV')
        ->assertDontSee('secret-token');

    QuillServer::actingAs($user)->tool(UpdateBillingSettingsTool::class, ['default_rate' => 150, 'invoice_prefix' => 'ACME'])->assertOk();

    expect($organization->billingSettings())
        ->invoice_prefix->toBe('ACME')
        ->default_rate->toBe('150.00')
        ->payment_terms_days->toBe(30)
        ->square_access_token->toBe('secret-token');
});

test('billing settings use the web validation', function () {
    [$user] = billingToolMember();

    QuillServer::actingAs($user)->tool(UpdateBillingSettingsTool::class, ['invoice_prefix' => 'IN-V'])->assertHasErrors(['invoice prefix']);
});

test('members cannot see or change billing settings', function () {
    [$user, $organization] = billingToolMember('Member');

    QuillServer::actingAs($user)->tool(ShowBillingSettingsTool::class)->assertHasErrors();
    QuillServer::actingAs($user)->tool(UpdateBillingSettingsTool::class, ['invoice_prefix' => 'NOPE'])->assertHasErrors();

    expect($organization->billingSettings()->invoice_prefix)->toBe('INV');
});

test('it lists, creates, updates and deletes work types', function () {
    [$user, $organization] = billingToolMember();
    WorkType::factory()->for($organization)->create(['name' => 'Design']);

    QuillServer::actingAs($user)->tool(ListWorkTypesTool::class)->assertOk()->assertSee('Design');

    QuillServer::actingAs($user)->tool(CreateWorkTypeTool::class, ['name' => ' Development ', 'color' => 'blue'])->assertOk();

    $workType = $organization->workTypes()->where('name', 'Development')->sole();
    expect($workType->color)->toBe('blue');

    QuillServer::actingAs($user)->tool(UpdateWorkTypeTool::class, ['work_type_id' => $workType->id, 'is_active' => false])->assertOk();

    expect($workType->fresh())->name->toBe('Development')->is_active->toBeFalse();

    QuillServer::actingAs($user)->tool(DeleteWorkTypeTool::class, ['work_type_id' => $workType->id])->assertOk();

    expect($workType->fresh())->toBeNull();
});

test('work type names are unique', function () {
    [$user, $organization] = billingToolMember();
    WorkType::factory()->for($organization)->create(['name' => 'Design']);

    QuillServer::actingAs($user)->tool(CreateWorkTypeTool::class, ['name' => 'Design'])->assertHasErrors(['That work type already exists.']);
});

test('members cannot manage work types', function () {
    [$user, $organization] = billingToolMember('Member');
    $workType = WorkType::factory()->for($organization)->create();

    QuillServer::actingAs($user)->tool(CreateWorkTypeTool::class, ['name' => 'Nope'])->assertHasErrors();
    QuillServer::actingAs($user)->tool(DeleteWorkTypeTool::class, ['work_type_id' => $workType->id])->assertHasErrors();

    expect($organization->workTypes()->count())->toBe(1);
});

test('work types of another organization are out of reach', function () {
    [$user] = billingToolMember();
    $foreign = WorkType::factory()->create();

    QuillServer::actingAs($user)->tool(DeleteWorkTypeTool::class, ['work_type_id' => $foreign->id])->assertHasErrors();

    expect($foreign->fresh())->not->toBeNull();
});
