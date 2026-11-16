<?php

namespace App\Http\Requests\Clients;

use App\Enums\BillingCycle;
use App\Enums\OrganizationCapability;
use App\Models\Client;
use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $organization = $this->route('current_organization');

        if (! $organization instanceof Organization || ! $this->managesBilling($organization) || ! $organization->canUse(OrganizationCapability::BillingCycles) || ! $this->has('billing_cycle')) {
            return;
        }

        $cycle = BillingCycle::tryFrom((string) $this->input('billing_cycle'));
        $startsOn = $this->input('billing_cycle_starts_on') ?: null;

        $this->merge([
            'billing_cycle_starts_on' => match (true) {
                $cycle === null || $cycle === BillingCycle::Monthly => null,
                default => $startsOn ?? Date::now($organization->billingTimezone())->toDateString(),
            },
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        $organization = $this->route('current_organization');
        $client = $this->route('client');

        if (! $organization instanceof Organization || ! $this->managesBilling($organization)) {
            return $rules;
        }

        $cyclesUsable = $organization->canUse(OrganizationCapability::BillingCycles);
        $cycle = $cyclesUsable && $this->has('billing_cycle')
            ? $this->input('billing_cycle')
            : ($client instanceof Client ? $client->billing_cycle : null);

        return [
            ...$rules,
            'billing_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('client_contacts', 'id')->where('client_id', $client instanceof Client ? $client->id : 0),
            ],
            ...($cyclesUsable ? [
                'billing_cycle' => [
                    'nullable',
                    Rule::enum(BillingCycle::class),
                    fn (string $attribute, mixed $value, Closure $fail) => $value !== null && ($this->has('billed_through_client_id') ? $this->input('billed_through_client_id') : ($client instanceof Client ? $client->billed_through_client_id : null)) !== null
                        ? $fail(__('A client billed through another cannot have its own billing cycle.'))
                        : null,
                ],
                'billing_cycle_starts_on' => ['nullable', 'date'],
            ] : []),
            'billing_cc_emails' => ['nullable', 'array'],
            'billing_cc_emails.*' => ['email:rfc'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'billed_through_client_id' => [
                'nullable',
                'integer',
                fn (string $attribute, mixed $value, Closure $fail) => $value !== null && $cycle !== null
                    ? $fail(__('A client with a billing cycle cannot be billed through another.'))
                    : null,
                Rule::exists('clients', 'id')->where('organization_id', $organization->id),
                Rule::notIn($client instanceof Client ? [$client->id] : []),
                Rule::exists('clients', 'id')->whereNull('billed_through_client_id'),
                fn (string $attribute, mixed $value, Closure $fail) => $client instanceof Client && $value !== null && $client->billedClients()->exists()
                    ? $fail(__('This client already bills other clients.'))
                    : null,
            ],
        ];
    }

    private function managesBilling(Organization $organization): bool
    {
        return $this->user() !== null && $organization->allowsInvoiceManagementBy($this->user());
    }
}
