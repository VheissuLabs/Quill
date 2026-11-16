<?php

namespace App\Support;

use App\Models\BillingSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SquareClient
{
    public const VERSION = '2026-10-15';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $accessToken,
        private readonly string $locationId,
    ) {}

    public static function for(BillingSetting $settings): self
    {
        $baseUrl = $settings->square_environment === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        return new self($baseUrl, (string) $settings->square_access_token, (string) $settings->square_location_id);
    }

    /** @return array{id: string, url: string, order_id: string} */
    public function createPaymentLink(string $name, int $amountCents, string $redirectUrl, string $note): array
    {
        $response = $this->client()->post('/v2/online-checkout/payment-links', [
            'idempotency_key' => (string) Str::uuid(),
            'quick_pay' => [
                'name' => $name,
                'price_money' => ['amount' => $amountCents, 'currency' => 'USD'],
                'location_id' => $this->locationId,
            ],
            'checkout_options' => [
                'redirect_url' => $redirectUrl,
                'accepted_payment_methods' => ['apple_pay' => true, 'google_pay' => true, 'cash_app_pay' => true],
            ],
            'payment_note' => $note,
        ]);

        $link = $this->checked($response)->json('payment_link');

        return [
            'id' => (string) $link['id'],
            'url' => (string) $link['url'],
            'order_id' => (string) $link['order_id'],
        ];
    }

    public function deletePaymentLink(string $id): void
    {
        $response = $this->client()->delete("/v2/online-checkout/payment-links/{$id}");

        if ($response->status() === 404) {
            return;
        }

        $this->checked($response);
    }

    /** @return array<string, mixed> */
    public function retrieveOrder(string $orderId): array
    {
        return $this->checked($this->client()->get("/v2/orders/{$orderId}"))->json('order') ?? [];
    }

    /** @return array<string, mixed> */
    public function retrievePayment(string $paymentId): array
    {
        return $this->checked($this->client()->get("/v2/payments/{$paymentId}"))->json('payment') ?? [];
    }

    public static function verifySignature(string $signatureKey, string $notificationUrl, string $body, ?string $signature): bool
    {
        if (blank($signature)) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $notificationUrl.$body, $signatureKey, true));

        return hash_equals($expected, $signature);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->accessToken)
            ->withHeaders(['Square-Version' => self::VERSION])
            ->acceptJson()
            ->asJson();
    }

    private function checked(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $codes = $response->collect('errors')
            ->map(fn (array $error): string => trim(($error['code'] ?? 'UNKNOWN').' '.($error['detail'] ?? '')))
            ->implode('; ');

        throw new SquareException("Square request failed ({$response->status()}): ".($codes !== '' ? $codes : 'no error detail'));
    }
}
