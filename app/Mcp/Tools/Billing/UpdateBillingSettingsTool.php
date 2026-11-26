<?php

namespace App\Mcp\Tools\Billing;

use App\Http\Requests\Billing\BillingSettingRequest;
use App\Mcp\Tools\QuillTool;
use App\Models\BillingSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Change the organization\'s billing settings. Send only the fields to change. Defaults apply to new invoices only. Switching the Square environment cancels open online checkouts; blank Square secrets keep the saved ones.')]
class UpdateBillingSettingsTool extends QuillTool
{
    public function handle(Request $request): Response
    {
        return $this->attempt(function () use ($request) {
            $organization = $this->organization($request);
            $this->authorize($request, 'update', [BillingSetting::class, $organization]);

            $settings = $organization->billingSettings();

            $validated = $this->formRequest(BillingSettingRequest::class, $request, ['current_organization' => $organization], 'PATCH', [
                ...$settings->only(['invoice_prefix', 'payment_terms_days']),
                ...collect($request->all())->except('organization')->all(),
            ]);

            $settings->saveSettings($validated->validated());

            return Response::json($settings->refresh()->only(['invoice_prefix', 'default_rate', 'payment_terms_days', 'tax_rate', 'payment_instructions', 'square_environment', 'square_location_id']));
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationArgument($schema),
            'invoice_prefix' => $schema->string()->description('Letters and digits before invoice numbers, e.g. INV gives INV-2026-0001.'),
            'default_rate' => $schema->number()->description('Hourly rate used when neither the project nor the client sets one.'),
            'payment_terms_days' => $schema->integer()->description('Days from issue until an invoice is due.'),
            'tax_rate' => $schema->number()->description('Default tax percentage for new invoices.'),
            'payment_instructions' => $schema->string()->description('How clients should pay, shown on invoices.'),
            'square_environment' => $schema->string()->enum(['sandbox', 'production']),
            'square_access_token' => $schema->string()->description('Square access token. Leave out to keep the saved one.'),
            'square_location_id' => $schema->string(),
            'square_webhook_signature_key' => $schema->string()->description('Square webhook signature key. Leave out to keep the saved one.'),
        ];
    }
}
