<?php

use App\Actions\Billing\OpenBillingPeriod;
use App\Enums\BillingCycle;
use App\Enums\LineUnit;
use App\Enums\OrganizationCapability;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

function automaticallyBilledWorkspace(): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->addMember($owner, 'Owner');

    $make = function (bool $lines, bool $payment = false) use ($organization): array {
        $client = Client::factory()->for($organization)->create([
            'billing_cycle' => BillingCycle::Monthly,
            'billing_opened_through' => '2026-11-30',
            'billing_cc_emails' => ['ap@example.com'],
            'payment_terms_days' => 14,
        ]);
        $invoice = Invoice::factory()->for($organization)->for($client)
            ->cycle(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'))
            ->create(['reminded_at' => now()]);

        if ($lines) {
            $invoice->lines()->create(['description' => 'Development', 'quantity' => '2', 'unit' => LineUnit::Hours, 'rate' => '100.00']);
        }

        if ($payment) {
            InvoicePayment::factory()->for($invoice)->create();
        }

        return [$client, $invoice];
    };

    return [$organization, $owner, $make];
}

function patchingModules($test, User $owner, Organization $organization, bool $billing, bool $cycles): void
{
    $test->actingAs($owner)->patch(route('organizations.modules.update', $organization), [
        'feedback_enabled' => true,
        'feedback_boards_enabled' => true,
        'feedback_voting_enabled' => true,
        'development_enabled' => true,
        'development_issues_enabled' => true,
        'development_sprints_enabled' => true,
        'development_workflow_enabled' => true,
        'teams_enabled' => true,
        'billing_enabled' => $billing,
        'billing_invoices_enabled' => true,
        'billing_billing_cycles_enabled' => $cycles,
    ])->assertRedirect();
}

dataset('ways to turn it off', [
    'the capability' => [true, false],
    'the whole module' => [false, true],
]);

test('turning automatic billing off removes it from every client', function (bool $billing, bool $cycles) {
    [$organization, $owner, $make] = automaticallyBilledWorkspace();
    [$emptyClient, $empty] = $make(false);
    [$timedClient, $timed] = $make(true);
    [$paidClient, $paid] = $make(false, true);
    [$sentClient, $sent] = $make(true);
    $sent->update(['closes_at' => null, 'sent_at' => now()]);
    $sentClient->update(['billing_opened_through' => '2026-11-30']);

    patchingModules($this, $owner, $organization, $billing, $cycles);

    expect($organization->fresh()->canUse(OrganizationCapability::BillingCycles))->toBeFalse()
        ->and(Invoice::find($empty->id))->toBeNull()
        ->and($timed->fresh())->closes_at->toBeNull()->reminded_at->toBeNull()
        ->and($timed->lines()->count())->toBe(1)
        ->and($paid->fresh())->closes_at->toBeNull()
        ->and($paid->payments()->count())->toBe(1)
        ->and($sent->fresh())->sent_at->not->toBeNull()->reminded_at->not->toBeNull();

    foreach ([$emptyClient, $timedClient, $paidClient, $sentClient] as $client) {
        expect($client->fresh())
            ->billing_cycle->toBeNull()
            ->billing_cycle_starts_on->toBeNull()
            ->billing_opened_through->toBeNull()
            ->billing_cc_emails->toBe(['ap@example.com'])
            ->payment_terms_days->toBe(14);
    }
})->with('ways to turn it off');

test('turning automatic billing back on opens nothing until a client is given a cycle', function () {
    $this->travelTo('2026-12-05 12:00:00');
    [$organization, $owner, $make] = automaticallyBilledWorkspace();
    [$client] = $make(false);

    patchingModules($this, $owner, $organization, true, false);
    patchingModules($this, $owner, $organization, true, true);

    app(OpenBillingPeriod::class)->handle($client->fresh(), CarbonImmutable::now());

    expect($client->fresh()->invoices()->count())->toBe(0);

    $client->fresh()->update(['billing_cycle' => BillingCycle::Monthly]);
    app(OpenBillingPeriod::class)->handle($client->fresh(), CarbonImmutable::now());

    expect($client->fresh()->invoices()->count())->toBe(1);
});

test('other workspaces keep their automatic billing', function () {
    [$organization, $owner, $make] = automaticallyBilledWorkspace();
    [, , $makeOther] = automaticallyBilledWorkspace();
    $make(false);
    [$otherClient, $otherInvoice] = $makeOther(false);

    patchingModules($this, $owner, $organization, true, false);

    expect($otherClient->fresh())->billing_cycle->toBe(BillingCycle::Monthly)->billing_opened_through->not->toBeNull()
        ->and($otherInvoice->fresh())->closes_at->not->toBeNull();
});
