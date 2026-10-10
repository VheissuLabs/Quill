<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyResendSignature
{
    private const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) $request->header('svix-id');
        $timestamp = (string) $request->header('svix-timestamp');

        if ($id === '' || ! ctype_digit($timestamp) || abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            abort(401);
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            "{$id}.{$timestamp}.{$request->getContent()}",
            (string) base64_decode(Str::after((string) config('services.resend.webhook_secret'), 'whsec_')),
            true
        ));

        $matches = collect(explode(' ', (string) $request->header('svix-signature')))
            ->map(fn (string $signature) => Str::after($signature, ','))
            ->contains(fn (string $signature) => hash_equals($expected, $signature));

        abort_unless($matches && config('services.resend.webhook_secret'), 401);

        return $next($request);
    }
}
