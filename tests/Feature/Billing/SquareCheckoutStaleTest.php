<?php

use App\Actions\Billing\RecordSquarePayment;
use App\Actions\Billing\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\SquareCheckoutStatus;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\SquareCheckout;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Http::fake(['*' => Http::response([])]));

function staleInvoice(?Organization $organization = null, string $linkId = 'PL1'): array
{
    $invoice = Invoice::factory()->when($organization, fn ($factory) => $factory->for($organization))->sent()->create();
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '1', 'unit' => 'hours', 'rate' => '1000.00']);
    $invoice->organization->billingSettings()->update(['square_access_token' => 'token', 'square_location_id' => 'L1']);
    $checkout = SquareCheckout::factory()->for($invoice)->create(['payment_link_id' => $linkId, 'order_id' => "O-{$linkId}"]);

    return [$invoice->refresh(), $checkout];
}

test('recording a manual payment cancels open checkouts', function () {
    [$invoice, $checkout] = staleInvoice();

    $invoice->payments()->create(['amount' => '100.00', 'paid_on' => '2026-11-16', 'method' => 'check']);

    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled);
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/payment-links/PL1'));
});

test('changing the total cancels open checkouts', function () {
    [$invoice, $checkout] = staleInvoice();

    $invoice->lines()->first()->update(['rate' => '2000.00']);
    $invoice->recalculate();

    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled);
});

test('voiding cancels open checkouts', function () {
    [$invoice, $checkout] = staleInvoice();

    app(VoidInvoice::class)->handle($invoice);

    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled);
});

test('unrelated changes keep open checkouts and pending ones survive', function () {
    [$invoice, $checkout] = staleInvoice();
    $pending = SquareCheckout::factory()->for($invoice)->create(['status' => SquareCheckoutStatus::Pending]);

    $invoice->update(['cc_emails' => ['finance@example.com']]);
    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Open);

    $invoice->payments()->create(['amount' => '100.00', 'paid_on' => '2026-11-16', 'method' => 'check']);
    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled)
        ->and($pending->refresh()->status)->toBe(SquareCheckoutStatus::Pending);
});

test('a square recorded payment cancels the other open checkouts but not its own', function () {
    Notification::fake();
    [$invoice, $own] = staleInvoice();
    $other = SquareCheckout::factory()->for($invoice)->create(['payment_link_id' => 'PL2', 'order_id' => 'O-PL2']);

    app(RecordSquarePayment::class)->handle($invoice->organization, [
        'id' => 'PAY1',
        'order_id' => $own->order_id,
        'status' => 'COMPLETED',
        'amount_money' => ['amount' => 100000, 'currency' => 'USD'],
        'source_type' => 'CARD',
        'created_at' => '2026-11-16T15:00:00.000Z',
    ]);

    expect($own->refresh()->status)->toBe(SquareCheckoutStatus::Paid)
        ->and($other->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
});

test('switching environment cancels every open checkout in the workspace only', function () {
    [$invoice, $checkout] = staleInvoice();
    [, $second] = staleInvoice($invoice->organization, 'PL2');
    [, $foreign] = staleInvoice(null, 'PL3');
    $user = User::factory()->create();
    $invoice->organization->addMember($user, 'Admin');

    $this->actingAs($user)
        ->patch(route('organizations.billing.update', $invoice->organization), ['invoice_prefix' => 'INV', 'payment_terms_days' => 30, 'square_environment' => 'production'])
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled)
        ->and($second->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled)
        ->and($foreign->refresh()->status)->toBe(SquareCheckoutStatus::Open)
        ->and($invoice->organization->billingSettings()->square_environment)->toBe('production');
});

test('saving the same environment keeps open checkouts', function () {
    [$invoice, $checkout] = staleInvoice();
    $user = User::factory()->create();
    $invoice->organization->addMember($user, 'Admin');
    $environment = $invoice->organization->billingSettings()->square_environment;

    $this->actingAs($user)
        ->patch(route('organizations.billing.update', $invoice->organization), ['invoice_prefix' => 'INV', 'payment_terms_days' => 30, 'square_environment' => $environment]);

    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Open);
});

test('an unreachable Square never breaks a cancelling save', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    [$invoice, $checkout] = staleInvoice();

    $invoice->payments()->create(['amount' => '100.00', 'paid_on' => '2026-11-16', 'method' => 'check']);

    expect($checkout->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled);
});
