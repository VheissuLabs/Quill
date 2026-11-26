<?php

namespace App\Http\Controllers\Billing;

use App\Enums\AddressType;
use App\Events\BillingChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\BillingSettingRequest;
use App\Http\Requests\Billing\WorkTypeRequest;
use App\Http\Resources\AddressResource;
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
            ]),
            'addresses' => BillingChanged::live(AddressResource::collection($currentOrganization->addresses()->get())->resolve(), $currentOrganization->id),
            'addressTypes' => AddressType::options(),
            'workTypes' => BillingChanged::live($currentOrganization->workTypes()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'color', 'is_active']), $currentOrganization->id),
            'colors' => WorkTypeRequest::colors(),
        ]);
    }

    public function update(BillingSettingRequest $request, Organization $currentOrganization): RedirectResponse
    {
        $currentOrganization->billingSettings()->saveSettings($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Billing settings saved.')]);

        return back();
    }
}
