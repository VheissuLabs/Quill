<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\BillingSettingRequest;
use App\Http\Requests\Billing\WorkTypeRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettingController extends Controller
{
    public function edit(Organization $currentOrganization): Response
    {
        $settings = $currentOrganization->billingSettings();

        return Inertia::render('billing/Settings', [
            'square' => [
                'environment' => $settings->square_environment,
                'hasAccessToken' => filled($settings->square_access_token),
                'locationId' => $settings->square_location_id,
                'hasSignatureKey' => filled($settings->square_webhook_signature_key),
                'webhookUrl' => $settings->webhookUrl(),
            ],
            'paymentInstructions' => $settings->payment_instructions,
            'settings' => $settings->only([
                'invoice_prefix',
                'default_rate',
                'payment_terms_days',
                'tax_rate',
                'default_notes',
                'default_terms',
                'from_address',
            ]),
            'workTypes' => $currentOrganization->workTypes()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'color', 'is_active']),
            'colors' => WorkTypeRequest::colors(),
        ]);
    }

    public function update(BillingSettingRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->billingSettings()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Billing settings saved.')]);

        return back();
    }
}
