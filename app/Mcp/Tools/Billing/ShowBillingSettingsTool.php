<?php

namespace App\Mcp\Tools\Billing;

use App\Mcp\Tools\QuillTool;
use App\Models\BillingSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Show the organization\'s billing settings: invoice number prefix, default hourly rate, payment terms, tax rate, from address, payment instructions and whether Square online payments are set up. Secrets are never returned.')]
class ShowBillingSettingsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'viewAny', [BillingSetting::class, $organization]);

            $settings = $organization->billingSettings();

            return Response::json([
                ...$settings->only(['invoice_prefix', 'default_rate', 'payment_terms_days', 'tax_rate', 'payment_instructions']),
                'square' => [
                    'environment' => $settings->square_environment,
                    'hasAccessToken' => filled($settings->square_access_token),
                    'locationId' => $settings->square_location_id,
                    'hasSignatureKey' => filled($settings->square_webhook_signature_key),
                    'webhookUrl' => $settings->webhookUrl(),
                ],
            ]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
        ];
    }
}
