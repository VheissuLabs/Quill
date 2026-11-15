<?php

use App\Enums\LineUnit;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;

function lineManager(): array
{
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->addMember($admin, 'Admin');

    return [$organization, $admin, Client::factory()->for($organization)->create()];
}

function lineRoute(string $name, Invoice $invoice, ?InvoiceLine $line = null): string
{
    return route($name, array_filter(['current_organization' => $invoice->organization, 'invoice' => $invoice, 'line' => $line]));
}

function draftInvoice(): array
{
    [$organization, $admin, $client] = lineManager();

    return [Invoice::factory()->for($organization)->for($client)->create(), $admin, $client, $organization];
}

test('a line for hours gets amount from quantity and rate', function () {
    [$invoice, $admin] = draftInvoice();

    $this->actingAs($admin)
        ->post(lineRoute('invoices.lines.store', $invoice), ['description' => 'Build', 'quantity' => '2.5', 'unit' => 'hours', 'rate' => '120'])
        ->assertRedirect();

    expect($invoice->lines()->first())->amount->toBe('300.00')->unit->toBe(LineUnit::Hours)
        ->and($invoice->fresh()->subtotal)->toBe('300.00');
});

test('a new line defaults its rate from the project then client then workspace', function () {
    [$invoice, $admin, $client, $organization] = draftInvoice();
    $organization->billingSettings()->update(['default_rate' => '50.00']);

    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice), ['description' => 'A', 'quantity' => '1']);
    expect($invoice->lines()->reorder()->latest('id')->first()->rate)->toBe('50.00');

    $client->update(['billing_rate' => '70.00']);
    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice->fresh()), ['description' => 'B', 'quantity' => '1']);
    expect($invoice->lines()->reorder()->latest('id')->first()->rate)->toBe('70.00');

    $project = Project::factory()->for($organization)->for($client)->create(['billing_rate' => '90.00']);
    $invoice->update(['project_id' => $project->id]);
    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice->fresh()), ['description' => 'C', 'quantity' => '1']);
    expect($invoice->lines()->reorder()->latest('id')->first()->rate)->toBe('90.00');
});

test('a rate is required when nothing supplies a default', function () {
    [$invoice, $admin] = draftInvoice();

    $this->actingAs($admin)
        ->post(lineRoute('invoices.lines.store', $invoice), ['description' => 'A', 'quantity' => '1'])
        ->assertSessionHasErrors('rate');
});

test('an issue line takes the issue title when description is blank', function () {
    [$invoice, $admin, $client, $organization] = draftInvoice();
    $project = Project::factory()->for($organization)->for($client)->create();
    $issue = Issue::factory()->for($project)->create(['title' => 'Fix the orbit']);

    $this->actingAs($admin)
        ->post(lineRoute('invoices.lines.store', $invoice), ['issue_id' => $issue->id, 'quantity' => '1', 'rate' => '10'])
        ->assertSessionHasNoErrors();

    expect($invoice->lines()->first())->description->toBe('Fix the orbit')->issue_id->toBe($issue->id);
});

test('issue must belong to a client billed on this invoice', function () {
    [$organization, $admin, $client] = lineManager();
    $kms = Client::factory()->for($organization)->create(['billed_through_client_id' => $client->id]);
    $invoice = Invoice::factory()->for($organization)->for($client)->create();
    $billed = Issue::factory()->for(Project::factory()->for($organization)->for($kms))->create();
    $stranger = Issue::factory()->for(Project::factory()->for($organization)->for(Client::factory()->for($organization)))->create();

    $this->actingAs($admin)
        ->post(lineRoute('invoices.lines.store', $invoice), ['description' => 'x', 'issue_id' => $billed->id, 'quantity' => '1', 'rate' => '10'])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(lineRoute('invoices.lines.store', $invoice), ['description' => 'x', 'issue_id' => $stranger->id, 'quantity' => '1', 'rate' => '10'])
        ->assertSessionHasErrors('issue_id');
});

test('work type must be in the workspace', function () {
    [$invoice, $admin, , $organization] = draftInvoice();
    $mine = WorkType::factory()->for($organization)->create();
    $foreign = WorkType::factory()->create();
    $payload = ['description' => 'x', 'quantity' => '1', 'rate' => '10'];

    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice), $payload + ['work_type_id' => $mine->id])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice), $payload + ['work_type_id' => $foreign->id])->assertSessionHasErrors('work_type_id');
});

test('reference_url must be a url up to 2048 chars', function () {
    [$invoice, $admin] = draftInvoice();
    $payload = ['description' => 'x', 'quantity' => '1', 'rate' => '10'];

    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice), $payload + ['reference_url' => 'not a url'])->assertSessionHasErrors('reference_url');
    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice), $payload + ['reference_url' => 'https://example.com/'.str_repeat('a', 2040)])->assertSessionHasErrors('reference_url');
    $this->actingAs($admin)->post(lineRoute('invoices.lines.store', $invoice), $payload + ['reference_url' => 'https://example.com/pr/1'])->assertSessionHasNoErrors();
});

test('a draft line can be edited and deleted', function () {
    [$invoice, $admin] = draftInvoice();
    $line = InvoiceLine::factory()->for($invoice)->create(['quantity' => '1.00', 'rate' => '100.00']);

    $this->actingAs($admin)->patch(lineRoute('invoices.lines.update', $invoice, $line), ['quantity' => '3', 'rate' => '10'])->assertRedirect();
    expect($line->fresh()->amount)->toBe('30.00')->and($invoice->fresh()->subtotal)->toBe('30.00');

    $this->actingAs($admin)->delete(lineRoute('invoices.lines.destroy', $invoice, $line))->assertRedirect();
    expect(InvoiceLine::count())->toBe(0)->and($invoice->fresh()->subtotal)->toBe('0.00');
});

test('a line from another invoice is not found', function () {
    [$invoice, $admin, $client, $organization] = draftInvoice();
    $other = Invoice::factory()->for($organization)->for($client)->create();
    $line = InvoiceLine::factory()->for($other)->create();

    $this->actingAs($admin)->patch(lineRoute('invoices.lines.update', $invoice, $line), ['description' => 'x'])->assertNotFound();
    $this->actingAs($admin)->delete(lineRoute('invoices.lines.destroy', $invoice, $line))->assertNotFound();
});

test('a sent invoice\'s line keeps its money', function () {
    [$organization, $admin, $client] = lineManager();
    $invoice = Invoice::factory()->sent()->for($organization)->for($client)->create();
    $line = InvoiceLine::factory()->for($invoice)->create(['quantity' => '2.00', 'rate' => '100.00']);
    $workType = WorkType::factory()->for($organization)->create();

    $this->actingAs($admin)
        ->patch(lineRoute('invoices.lines.update', $invoice, $line), ['quantity' => '9'])
        ->assertSessionHasErrors(['quantity' => __('This invoice has been sent; its amounts can no longer change.')]);

    $this->actingAs($admin)
        ->patch(lineRoute('invoices.lines.update', $invoice, $line), ['description' => 'Renamed', 'reference_url' => 'https://example.com/1', 'work_type_id' => $workType->id])
        ->assertSessionHasNoErrors();

    expect($line->fresh())->description->toBe('Renamed')->reference_url->toBe('https://example.com/1')->work_type_id->toBe($workType->id)->quantity->toBe('2.00');

    $this->actingAs($admin)
        ->post(lineRoute('invoices.lines.store', $invoice), ['description' => 'x', 'quantity' => '1', 'rate' => '1'])
        ->assertForbidden();
    $this->actingAs($admin)->delete(lineRoute('invoices.lines.destroy', $invoice, $line))->assertForbidden();
    expect($invoice->lines()->count())->toBe(1);
});

test('a void invoice\'s lines cannot be touched', function () {
    [$organization, $admin, $client] = lineManager();
    $invoice = Invoice::factory()->void()->for($organization)->for($client)->create();
    $line = InvoiceLine::factory()->for($invoice)->create();

    $this->actingAs($admin)->patch(lineRoute('invoices.lines.update', $invoice, $line), ['description' => 'x'])->assertForbidden();
    $this->actingAs($admin)->delete(lineRoute('invoices.lines.destroy', $invoice, $line))->assertForbidden();
});

test('lines can be reordered', function () {
    [$invoice, $admin] = draftInvoice();
    [$a, $b, $c] = InvoiceLine::factory()->count(3)->for($invoice)->sequence(['sort_order' => 0], ['sort_order' => 1], ['sort_order' => 2])->create()->all();

    $this->actingAs($admin)
        ->put(lineRoute('invoices.lines.order.update', $invoice), ['line_ids' => [$c->id, $a->id, $b->id]])
        ->assertRedirect();

    expect($invoice->lines()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);

    $stranger = InvoiceLine::factory()->create();
    $this->actingAs($admin)
        ->put(lineRoute('invoices.lines.order.update', $invoice), ['line_ids' => [$stranger->id, $a->id, $b->id]])
        ->assertSessionHasErrors('line_ids.0');
});

test('issue lookup for an invoice only returns that invoice\'s billable issues', function () {
    [$organization, $admin, $client] = lineManager();
    $kms = Client::factory()->for($organization)->create(['billed_through_client_id' => $client->id]);
    $invoice = Invoice::factory()->for($organization)->for($client)->create();
    Issue::factory()->for(Project::factory()->for($organization)->for($kms))->create(['title' => 'Orbit billed']);
    Issue::factory()->for(Project::factory()->for($organization)->for(Client::factory()->for($organization)))->create(['title' => 'Orbit stranger']);
    $url = fn (array $extra) => route('issues.lookup', ['current_organization' => $organization, 'q' => 'orbit'] + $extra);

    expect(collect($this->actingAs($admin)->getJson($url(['invoice' => $invoice->id]))->json())->pluck('title')->all())->toBe(['Orbit billed'])
        ->and($this->actingAs($admin)->getJson($url([]))->json())->toHaveCount(2);

    $foreign = Invoice::factory()->create();
    $this->actingAs($admin)->getJson($url(['invoice' => $foreign->id]))->assertJsonValidationErrors('invoice');
});
