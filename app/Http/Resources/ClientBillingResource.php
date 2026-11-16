<?php

namespace App\Http\Resources;

use App\Enums\BillingCycle;
use App\Enums\OrganizationCapability;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientBillingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'rate' => $this->billing_rate,
            'contactId' => $this->billing_contact_id,
            'billedThroughClientId' => $this->billed_through_client_id,
            'ccEmails' => $this->billing_cc_emails ?? [],
            'paymentTermsDays' => $this->payment_terms_days,
            ...($this->organization->canUse(OrganizationCapability::BillingCycles) ? [
                'cycle' => $this->billing_cycle?->value,
                'cycleStartsOn' => $this->billing_cycle_starts_on?->toDateString(),
                'currentPeriod' => $this->currentBillingPeriod(),
                'cycles' => array_map(fn (BillingCycle $cycle) => ['value' => $cycle->value, 'label' => $cycle->label()], BillingCycle::cases()),
            ] : []),
            'contacts' => $this->contacts()->orderBy('name')->get(['id', 'name', 'email']),
            'billedThroughOptions' => $this->organization->clients()->whereKeyNot($this->id)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
