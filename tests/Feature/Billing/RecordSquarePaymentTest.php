<?php

use App\Actions\Billing\RecordSquarePayment;
use App\Enums\InvoiceStatus;
use App\Enums\LineUnit;
use App\Enums\PaymentMethod;
use App\Enums\SquareCheckoutStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationModuleSetting;
use App\Models\SquareCheckout;
use App\Models\User;
use App\Notifications\InvoicePaymentReceived;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Notification::fake());

function recorderWorkspace(string $state = 'sent', string $orderId = 'O1'): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->create(['timezone' => 'America/Chicago']);
    $organization->addMember($owner, 'Owner');
    $organization->billingSettings()->update(['square_access_token' => 'token', 'square_location_id' => 'L1']);
    $client = Client::factory()->for($organization)->create(['name' => 'Hamill Inc']);

    $invoice = Invoice::factory()->for($organization)->for($client)->{$state}()->create(['number' => 'INV-2026-0001']);
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '1', 'unit' => LineUnit::Hours, 'rate' => '1299.00']);
    $checkout = SquareCheckout::factory()->for($invoice)->create(['order_id' => $orderId, 'amount' => '1299.00']);

    return [$invoice->fresh(), $checkout, $owner];
}

function squarePaymentObject(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'PAY1',
        'order_id' => 'O1',
        'status' => 'COMPLETED',
        'amount_money' => ['amount' => 129900, 'currency' => 'USD'],
        'source_type' => 'CARD',
        'created_at' => '2026-11-16T15:00:00.000Z',
    ], $overrides);
}

function recordSquare(Invoice $invoice, array $overrides = []): void
{
    app(RecordSquarePayment::class)->handle($invoice->organization, squarePaymentObject($overrides));
}

test('a completed card payment is recorded once', function () {
    [$invoice, $checkout, $owner] = recorderWorkspace();

    recordSquare($invoice);
    recordSquare($invoice);

    $payment = $invoice->payments()->sole();
    expect($payment)->amount->toBe('1299.00')->method->toBe(PaymentMethod::Card)->reference->toBe('PAY1')->square_payment_id->toBe('PAY1')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($checkout->fresh())->status->toBe(SquareCheckoutStatus::Paid)->square_payment_id->toBe('PAY1');
    Notification::assertSentToTimes($owner, InvoicePaymentReceived::class, 1);
    Notification::assertSentTo($owner, InvoicePaymentReceived::class, fn (InvoicePaymentReceived $notification) => $notification->toArray($owner)['title'] === 'Hamill Inc paid $1,299.00 on INV-2026-0001 by card.');
});

test('ACH pending then completed', function () {
    [$invoice, $checkout] = recorderWorkspace();

    recordSquare($invoice, ['status' => 'PENDING', 'source_type' => 'BANK_ACCOUNT']);

    expect($invoice->payments()->count())->toBe(0)
        ->and($checkout->fresh())->status->toBe(SquareCheckoutStatus::Pending)->square_payment_id->toBe('PAY1');
    Notification::assertNothingSent();

    recordSquare($invoice, ['source_type' => 'BANK_ACCOUNT']);

    expect($invoice->payments()->sole()->method)->toBe(PaymentMethod::Ach)
        ->and($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Paid);
});

test('a failed ACH reopens the checkout', function () {
    [$invoice, $checkout] = recorderWorkspace();

    recordSquare($invoice, ['status' => 'PENDING', 'source_type' => 'BANK_ACCOUNT']);
    recordSquare($invoice, ['status' => 'FAILED', 'source_type' => 'BANK_ACCOUNT']);

    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Open)
        ->and($invoice->payments()->count())->toBe(0);
});

test('Cash App maps to cash_app, Apple Pay card to card, other wallets to other', function (array $source, PaymentMethod $method) {
    [$invoice] = recorderWorkspace();

    recordSquare($invoice, $source);

    expect($invoice->payments()->sole()->method)->toBe($method);
})->with([
    'cash app' => [['source_type' => 'WALLET', 'wallet_details' => ['brand' => 'CASH_APP']], PaymentMethod::CashApp],
    'apple pay' => [['source_type' => 'CARD', 'card_details' => ['card' => ['card_brand' => 'VISA']], 'wallet_details' => ['brand' => 'APPLE_PAY']], PaymentMethod::Card],
    'other wallet' => [['source_type' => 'WALLET', 'wallet_details' => ['brand' => 'AFTERPAY']], PaymentMethod::Other],
]);

test('a deleted payment is not re-created', function () {
    [$invoice] = recorderWorkspace();

    recordSquare($invoice);
    $invoice->payments()->sole()->delete();
    recordSquare($invoice);

    expect($invoice->payments()->count())->toBe(0);
});

test("another workspace's order is ignored", function () {
    [$invoice, $checkout] = recorderWorkspace();
    $other = Organization::factory()->create();

    $result = app(RecordSquarePayment::class)->handle($other, squarePaymentObject());

    expect($result)->toBeNull()
        ->and($invoice->payments()->count())->toBe(0)
        ->and($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Open);
    Notification::assertNothingSent();
});

test('an overpayment is recorded and the notice says so', function () {
    [$invoice, , $owner] = recorderWorkspace();

    recordSquare($invoice, ['amount_money' => ['amount' => 139900]]);

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Paid)->amount_paid->toBe('1399.00');
    Notification::assertSentTo($owner, InvoicePaymentReceived::class, fn (InvoicePaymentReceived $notification) => str_contains($notification->toArray($owner)['title'], 'overpaid by $100.00'));
});

test('a payment on a void invoice is recorded and flagged', function () {
    [$invoice, , $owner] = recorderWorkspace('void');

    recordSquare($invoice);

    expect($invoice->payments()->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
    Notification::assertSentTo($owner, InvoicePaymentReceived::class, fn (InvoicePaymentReceived $notification) => str_contains($notification->toArray($owner)['title'], 'void'));
});

test('partial amount leaves the invoice Partial', function () {
    [$invoice] = recorderWorkspace();

    recordSquare($invoice, ['amount_money' => ['amount' => 50005]]);

    expect($invoice->fresh())->status->toBe(InvoiceStatus::Partial)->amount_paid->toBe('500.05');
});

test('paid_on uses the billing timezone', function () {
    [$invoice] = recorderWorkspace();

    recordSquare($invoice, ['created_at' => '2026-11-16T03:00:00Z']);

    expect($invoice->payments()->sole()->paid_on->toDateString())->toBe('2026-11-15');
});

function fakeSquareOrder(string $status = 'COMPLETED'): void
{
    Http::fake([
        '*/v2/orders/O1' => Http::response(['order' => ['id' => 'O1', 'tenders' => [['id' => 'PAY1']]]]),
        '*/v2/payments/PAY1' => Http::response(['payment' => squarePaymentObject(['status' => $status])]),
    ]);
}

test('returning with orderId records the completed payment', function () {
    fakeSquareOrder();
    [$invoice] = recorderWorkspace();

    $this->get($invoice->publicUrl().'?orderId=O1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invoice.status', InvoiceStatus::Paid->value)->where('invoice.amountDue', '0.00'));

    expect($invoice->payments()->sole()->square_payment_id)->toBe('PAY1');
});

test('returning while the payment is pending shows it processing', function () {
    fakeSquareOrder('PENDING');
    [$invoice] = recorderWorkspace();

    $this->get($invoice->publicUrl().'?orderId=O1')
        ->assertInertia(fn (Assert $page) => $page->where('invoice.paymentProcessing', true)->where('invoice.canPayOnline', false));
});

test('a plain visit makes no Square calls', function () {
    Http::fake();
    [$invoice] = recorderWorkspace();

    $this->get($invoice->publicUrl())->assertOk();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'squareup'));
});

test('an orderId for another invoice is ignored', function () {
    Http::fake();
    [$invoice] = recorderWorkspace();
    [$other] = recorderWorkspace(orderId: 'O2');

    $this->get($invoice->publicUrl().'?orderId=O2')->assertOk();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'squareup'));
    expect($other->payments()->count())->toBe(0);
});

test('a Square error on return still shows the invoice', function () {
    Http::fake(['*' => Http::response(['errors' => [['code' => 'INTERNAL_SERVER_ERROR']]], 500)]);
    [$invoice] = recorderWorkspace();

    $this->get($invoice->publicUrl().'?orderId=O1')->assertOk();

    expect($invoice->payments()->count())->toBe(0);
});

test('a connection failure on return still shows the invoice', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    [$invoice] = recorderWorkspace();

    $this->get($invoice->publicUrl().'?orderId=O1')->assertOk();

    expect($invoice->payments()->count())->toBe(0);
});

test('a failed attempt for a different payment does not reopen a pending checkout', function () {
    [$invoice, $checkout] = recorderWorkspace();

    recordSquare($invoice, ['status' => 'PENDING', 'source_type' => 'BANK_ACCOUNT']);
    recordSquare($invoice, ['id' => 'PAY2', 'status' => 'FAILED']);

    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Pending);
});

function pendingWorkspace(string $orderId = 'O1', string $paymentId = 'PAY1'): array
{
    [$invoice, $checkout] = recorderWorkspace(orderId: $orderId);
    $checkout->update(['status' => SquareCheckoutStatus::Pending, 'square_payment_id' => $paymentId]);

    return [$invoice, $checkout->fresh()];
}

function fakeSquarePayment(string $status, string $paymentId = 'PAY1', string $orderId = 'O1'): void
{
    Http::fake(["*/v2/payments/{$paymentId}" => Http::response(['payment' => squarePaymentObject(['id' => $paymentId, 'order_id' => $orderId, 'status' => $status])])]);
}

test('billing:run records a pending payment that has since completed', function () {
    fakeSquarePayment('COMPLETED');
    [$invoice, $checkout] = pendingWorkspace();

    $this->artisan('billing:run')->assertSuccessful();

    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Paid)
        ->and($checkout->fresh()->checked_at)->not->toBeNull()
        ->and($invoice->payments()->sole()->square_payment_id)->toBe('PAY1');
});

test('billing:run reopens a pending checkout whose payment failed', function () {
    fakeSquarePayment('FAILED');
    [, $checkout] = pendingWorkspace();

    $this->artisan('billing:run')->assertSuccessful();

    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Open);
});

test('a checkout checked within the last hour is left alone', function () {
    Http::fake();
    [, $checkout] = pendingWorkspace();
    $checkout->update(['checked_at' => now()->subMinutes(30)]);

    $this->artisan('billing:run')->assertSuccessful();

    Http::assertNothingSent();
    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Pending);
});

test('a checkout last checked over an hour ago is checked again', function () {
    fakeSquarePayment('COMPLETED');
    [, $checkout] = pendingWorkspace();
    $checkout->update(['checked_at' => now()->subMinutes(61)]);

    $this->artisan('billing:run')->assertSuccessful();

    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Paid);
});

test('a Square error is logged and the other checkouts are still checked', function () {
    Log::spy();
    Http::fake([
        '*/v2/payments/PAY1' => Http::response(['errors' => [['code' => 'INTERNAL_SERVER_ERROR']]], 500),
        '*/v2/payments/PAY2' => Http::response(['payment' => squarePaymentObject(['id' => 'PAY2', 'order_id' => 'O2'])]),
    ]);
    [, $failing] = pendingWorkspace('O1', 'PAY1');
    [, $working] = pendingWorkspace('O2', 'PAY2');

    $this->artisan('billing:run')->assertSuccessful();

    expect($failing->fresh()->status)->toBe(SquareCheckoutStatus::Pending)
        ->and($working->fresh()->status)->toBe(SquareCheckoutStatus::Paid);
    Log::shouldHaveReceived('warning')->once();
});

test('an unreachable Square leaves the checkout pending', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    [, $checkout] = pendingWorkspace();

    $this->artisan('billing:run')->assertSuccessful();

    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Pending);
});

test('workspaces without invoices or Square set up are skipped', function (Closure $arrange) {
    Http::fake();
    [$invoice] = pendingWorkspace();
    $arrange($invoice->organization);

    $this->artisan('billing:run')->assertSuccessful();

    Http::assertNothingSent();
})->with([
    'invoices off' => [fn ($organization) => OrganizationModuleSetting::factory()->create(['organization_id' => $organization->id, 'module' => 'billing', 'capability' => 'invoices', 'enabled' => false])],
    'square not configured' => [fn ($organization) => $organization->billingSettings()->update(['square_access_token' => null])],
]);

test('returning to a pending checkout re-reads the payment', function () {
    Http::fake([
        '*/v2/orders/O1' => Http::response(['order' => ['id' => 'O1', 'tenders' => [['id' => 'PAY1']]]]),
        '*/v2/payments/PAY1' => Http::response(['payment' => squarePaymentObject()]),
    ]);
    [$invoice, $checkout] = pendingWorkspace();

    $this->get($invoice->publicUrl().'?orderId=O1')
        ->assertInertia(fn (Assert $page) => $page->where('invoice.amountDue', '0.00'));

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v2/payments/PAY1'));
    expect($checkout->fresh()->status)->toBe(SquareCheckoutStatus::Paid);
});
