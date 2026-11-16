<?php

use App\Models\BillingSetting;
use App\Support\SquareClient;
use App\Support\SquareException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function squareSettings(string $environment = 'sandbox'): BillingSetting
{
    return new BillingSetting([
        'square_environment' => $environment,
        'square_access_token' => 'secret-token',
        'square_location_id' => 'L1',
    ]);
}

test('it creates a payment link', function () {
    Http::fake([
        '*' => Http::response(['payment_link' => ['id' => 'PL1', 'url' => 'https://square.link/u/abc', 'order_id' => 'O1', 'version' => 1]]),
    ]);

    $link = SquareClient::for(squareSettings())
        ->createPaymentLink('Invoice INV-2026-0001', 129900, 'https://quill.test/done', 'INV-2026-0001');

    expect($link)->toBe(['id' => 'PL1', 'url' => 'https://square.link/u/abc', 'order_id' => 'O1']);

    Http::assertSent(function (Request $request) {
        $data = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://connect.squareupsandbox.com/v2/online-checkout/payment-links'
            && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request->hasHeader('Square-Version', SquareClient::VERSION)
            && filled($data['idempotency_key'])
            && $data['quick_pay']['name'] === 'Invoice INV-2026-0001'
            && $data['quick_pay']['price_money'] === ['amount' => 129900, 'currency' => 'USD']
            && $data['quick_pay']['location_id'] === 'L1'
            && $data['checkout_options']['redirect_url'] === 'https://quill.test/done'
            && $data['checkout_options']['accepted_payment_methods'] === ['apple_pay' => true, 'google_pay' => true, 'cash_app_pay' => true]
            && $data['payment_note'] === 'INV-2026-0001';
    });
});

test('production uses the production host', function () {
    Http::fake(['*' => Http::response(['order' => ['id' => 'O1']])]);

    SquareClient::for(squareSettings('production'))->retrieveOrder('O1');

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->url() === 'https://connect.squareup.com/v2/orders/O1');
});

test('it retrieves a payment', function () {
    Http::fake(['*' => Http::response(['payment' => ['id' => 'P1', 'status' => 'COMPLETED']])]);

    expect(SquareClient::for(squareSettings())->retrievePayment('P1'))->toBe(['id' => 'P1', 'status' => 'COMPLETED']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://connect.squareupsandbox.com/v2/payments/P1');
});

test('errors raise SquareException without the token in the message', function () {
    Http::fake(['*' => Http::response(['errors' => [['code' => 'UNAUTHORIZED', 'detail' => 'Bad token']]], 401)]);

    try {
        SquareClient::for(squareSettings())->retrieveOrder('O1');
        $this->fail('Expected SquareException');
    } catch (SquareException $exception) {
        expect($exception->getMessage())->not->toContain('secret-token')->toContain('UNAUTHORIZED');
    }
});

test('deleting a payment link sends DELETE', function () {
    Http::fake(['*' => Http::response([])]);

    SquareClient::for(squareSettings())->deletePaymentLink('PL1');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
        && $request->url() === 'https://connect.squareupsandbox.com/v2/online-checkout/payment-links/PL1');
});

test('deleting a missing link is fine', function () {
    Http::fake(['*' => Http::response(['errors' => [['code' => 'NOT_FOUND']]], 404)]);

    SquareClient::for(squareSettings())->deletePaymentLink('PL1');

    Http::assertSentCount(1);
});

test('signatures verify', function () {
    $url = 'https://quill.test/webhooks/square/tok';
    $body = '{"type":"payment.updated"}';
    $signature = base64_encode(hash_hmac('sha256', $url.$body, 'key', true));

    expect(SquareClient::verifySignature('key', $url, $body, $signature))->toBeTrue()
        ->and(SquareClient::verifySignature('key', $url, $body.'x', $signature))->toBeFalse()
        ->and(SquareClient::verifySignature('key', $url, $body, null))->toBeFalse();
});

test('a connection failure becomes a Square exception without secrets', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 with Bearer secret-token'));

    $call = fn () => SquareClient::for(squareSettings())->retrievePayment('PAY1');

    expect($call)->toThrow(SquareException::class, 'Square could not be reached.');

    try {
        $call();
    } catch (SquareException $exception) {
        expect($exception->getMessage())->not->toContain('secret-token');
    }
});
