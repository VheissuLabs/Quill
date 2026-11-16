<?php

use App\Actions\Billing\RecordSquarePayment;
use App\Enums\LineUnit;
use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\SquareCheckout;
use App\Models\User;
use App\Notifications\InvoicePaymentReceived;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Notification::fake());

function webhookSettings(): BillingSetting
{
    $invoice = Invoice::factory()->sent()->create();
    $invoice->lines()->create(['description' => 'Development', 'quantity' => '1', 'unit' => LineUnit::Hours, 'rate' => '100.00']);
    SquareCheckout::factory()->for($invoice)->create(['order_id' => 'O1']);

    $settings = $invoice->organization->billingSettings();
    $settings->update(['square_access_token' => 'token', 'square_location_id' => 'L1', 'square_webhook_signature_key' => 'signature-key']);

    return $settings->fresh();
}

function webhookBody(string $type = 'payment.updated'): string
{
    return (string) json_encode([
        'merchant_id' => 'M1',
        'type' => $type,
        'event_id' => 'E1',
        'data' => ['type' => 'payment', 'id' => 'PAY1', 'object' => ['payment' => [
            'id' => 'PAY1',
            'order_id' => 'O1',
            'status' => 'COMPLETED',
            'amount_money' => ['amount' => 10000, 'currency' => 'USD'],
            'source_type' => 'CARD',
            'created_at' => '2026-11-16T15:00:00Z',
        ]]],
    ]);
}

function postWebhook(BillingSetting $settings, string $body, ?string $signature): TestResponse
{
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($signature !== null) {
        $headers['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] = $signature;
    }

    return test()->call('POST', "/webhooks/square/{$settings->square_webhook_token}", server: $headers, content: $body);
}

function squareSignature(BillingSetting $settings, string $body): string
{
    return base64_encode(hash_hmac('sha256', config('app.url').'/webhooks/square/'.$settings->square_webhook_token.$body, 'signature-key', true));
}

test('a signed payment.updated is recorded', function () {
    $settings = webhookSettings();
    $body = webhookBody();

    postWebhook($settings, $body, squareSignature($settings, $body))->assertOk();

    expect($settings->organization->invoices()->sole()->payments()->sole()->square_payment_id)->toBe('PAY1');
});

test('bad or missing signature is forbidden and records nothing', function (?string $signature) {
    $settings = webhookSettings();

    postWebhook($settings, webhookBody(), $signature)->assertForbidden();

    expect($settings->organization->invoices()->sole()->payments()->count())->toBe(0);
})->with(['bad' => ['bm90LWl0'], 'missing' => [null]]);

test('an unknown token is not found', function () {
    $this->postJson('/webhooks/square/unknown', [])->assertNotFound();
});

test('other event types are acknowledged and ignored', function () {
    $settings = webhookSettings();
    $body = webhookBody('refund.updated');

    postWebhook($settings, $body, squareSignature($settings, $body))->assertOk();

    expect($settings->organization->invoices()->sole()->payments()->count())->toBe(0);
});

test('a recording error answers 500 so Square retries, and is reported', function () {
    Exceptions::fake();
    $settings = webhookSettings();
    $body = webhookBody();
    $this->mock(RecordSquarePayment::class)->shouldReceive('handle')->andThrow(new RuntimeException('database went away'));

    postWebhook($settings, $body, squareSignature($settings, $body))->assertServerError();

    Exceptions::assertReported(RuntimeException::class);
});

test('the payment notice still goes out when Square cannot be reached afterwards', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    $settings = webhookSettings();
    $invoice = $settings->organization->invoices()->sole();
    SquareCheckout::factory()->for($invoice)->create(['order_id' => 'O2']);
    $owner = User::factory()->create();
    $settings->organization->addMember($owner, 'Owner');
    $body = webhookBody();

    postWebhook($settings, $body, squareSignature($settings, $body))->assertOk();

    expect($invoice->payments()->sole()->square_payment_id)->toBe('PAY1');
    Notification::assertSentTo($owner, InvoicePaymentReceived::class);
});
