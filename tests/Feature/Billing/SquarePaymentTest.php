<?php

use App\Enums\InvoiceStatus;
use App\Enums\SquareCheckoutStatus;
use App\Models\Invoice;
use App\Models\SquareCheckout;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function payableInvoice(string $rate = '1299.00', ?string $instructions = null): Invoice
{
    $invoice = Invoice::factory()->sent()->create();
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '1', 'unit' => 'hours', 'rate' => $rate]);
    $invoice->organization->billingSettings()->update([
        'square_access_token' => 'token',
        'square_location_id' => 'L1',
        'payment_instructions' => $instructions,
    ]);

    return $invoice->refresh();
}

function fakeSquareLink(string $id = 'PL1', string $orderId = 'O1'): void
{
    Http::fake([
        '*/v2/online-checkout/payment-links' => Http::response(['payment_link' => ['id' => $id, 'url' => "https://square.link/u/{$id}", 'order_id' => $orderId]]),
        '*/v2/online-checkout/payment-links/*' => Http::response([]),
    ]);
}

test('a client starts a checkout for the amount due', function () {
    fakeSquareLink();
    $invoice = payableInvoice();

    $this->post(route('invoices.public.pay', $invoice->public_token))->assertRedirect('https://square.link/u/PL1');

    $checkout = $invoice->squareCheckouts()->sole();
    expect($checkout->status)->toBe(SquareCheckoutStatus::Open)
        ->and($checkout->amount)->toBe('1299.00')
        ->and($checkout->order_id)->toBe('O1')
        ->and($checkout->payment_link_id)->toBe('PL1');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request['quick_pay']['price_money']['amount'] === 129900
        && $request['checkout_options']['redirect_url'] === $invoice->publicUrl()
        && $request['payment_note'] === $invoice->number);
});

test('an inertia request is sent to square with a location redirect', function () {
    fakeSquareLink();
    $invoice = payableInvoice();

    $this->post(route('invoices.public.pay', $invoice->public_token), [], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://square.link/u/PL1');
});

test('starting again cancels the earlier checkout and deletes its link', function () {
    fakeSquareLink('PL2', 'O2');
    $invoice = payableInvoice();
    $earlier = SquareCheckout::factory()->create(['invoice_id' => $invoice->id, 'payment_link_id' => 'PL1', 'order_id' => 'O1']);

    $this->post(route('invoices.public.pay', $invoice->public_token));

    expect($earlier->refresh()->status)->toBe(SquareCheckoutStatus::Cancelled)
        ->and($invoice->squareCheckouts()->where('status', SquareCheckoutStatus::Open)->sole()->payment_link_id)->toBe('PL2');
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/payment-links/PL1'));
});

test('a failing link deletion does not stop a new checkout', function () {
    Http::fake([
        '*/payment-links/PL1' => Http::response(['errors' => [['code' => 'INTERNAL_SERVER_ERROR']]], 500),
        '*/payment-links' => Http::response(['payment_link' => ['id' => 'PL2', 'url' => 'https://square.link/u/PL2', 'order_id' => 'O2']]),
    ]);
    $invoice = payableInvoice();
    SquareCheckout::factory()->create(['invoice_id' => $invoice->id, 'payment_link_id' => 'PL1']);

    $this->post(route('invoices.public.pay', $invoice->public_token))->assertRedirect('https://square.link/u/PL2');
});

test('pay is refused when it does not apply', function (Closure $arrange) {
    Http::fake();
    $invoice = payableInvoice();
    $arrange($invoice);

    $this->post(route('invoices.public.pay', $invoice->public_token))
        ->assertRedirect($invoice->publicUrl())
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "Online payment isn't available right now. Please try again or use another way to pay."]);

    Http::assertNothingSent();
    expect($invoice->squareCheckouts()->count())->toBe(0);
})->with([
    'draft' => fn (Invoice $invoice) => $invoice->forceFill(['sent_at' => null, 'status' => InvoiceStatus::Draft])->save(),
    'void' => fn (Invoice $invoice) => $invoice->forceFill(['status' => InvoiceStatus::Void])->save(),
    'paid' => fn (Invoice $invoice) => $invoice->forceFill(['status' => InvoiceStatus::Paid])->save(),
    'zero due' => fn (Invoice $invoice) => $invoice->forceFill(['amount_paid' => $invoice->total])->save(),
    'unconfigured' => fn (Invoice $invoice) => $invoice->organization->billingSettings()->update(['square_access_token' => null]),
]);

test('pay is refused while a payment is pending', function () {
    Http::fake();
    $invoice = payableInvoice();
    SquareCheckout::factory()->create(['invoice_id' => $invoice->id, 'status' => SquareCheckoutStatus::Pending]);

    $this->post(route('invoices.public.pay', $invoice->public_token))
        ->assertRedirect($invoice->publicUrl())
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "Online payment isn't available right now. Please try again or use another way to pay."]);

    Http::assertNothingSent();
});

test('a Square error sends the client back with a message', function () {
    Http::fake(['*' => Http::response(['errors' => [['code' => 'UNAUTHORIZED']]], 401)]);
    $invoice = payableInvoice();

    $this->post(route('invoices.public.pay', $invoice->public_token))
        ->assertRedirect($invoice->publicUrl())
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "Online payment isn't available right now. Please try again or use another way to pay."]);

    expect($invoice->squareCheckouts()->count())->toBe(0);
});

test('the public page offers pay only when it applies', function (InvoiceStatus $status, bool $expected) {
    $invoice = payableInvoice();
    $invoice->forceFill(['status' => $status])->save();

    $this->get($invoice->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('invoice.canPayOnline', $expected)->where('invoice.paymentProcessing', false));
})->with([
    [InvoiceStatus::Sent, true],
    [InvoiceStatus::Partial, true],
    [InvoiceStatus::Overdue, true],
    [InvoiceStatus::Paid, false],
    [InvoiceStatus::Void, false],
]);

test('the public page does not offer pay when square is not configured', function () {
    $invoice = payableInvoice();
    $invoice->organization->billingSettings()->update(['square_location_id' => null]);

    $this->get($invoice->publicUrl())->assertInertia(fn (Assert $page) => $page->where('invoice.canPayOnline', false));
});

test('the public page shows processing while a payment is pending', function () {
    $invoice = payableInvoice();
    SquareCheckout::factory()->create(['invoice_id' => $invoice->id, 'status' => SquareCheckoutStatus::Pending]);

    $this->get($invoice->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('invoice.canPayOnline', false)->where('invoice.paymentProcessing', true));
});

test('payment instructions show on unpaid invoices', function () {
    $invoice = payableInvoice(instructions: 'Wire to account 123');

    $this->get($invoice->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('invoice.paymentInstructions', 'Wire to account 123'));

    $invoice->forceFill(['status' => InvoiceStatus::Paid])->save();

    $this->get($invoice->publicUrl())
        ->assertInertia(fn (Assert $page) => $page->where('invoice.paymentInstructions', null));
});
