<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Models\Organization;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        $organization = $this->route('current_organization');
        $client = $this->route('client');

        if (! $organization instanceof Organization || ! $this->user() || ! $organization->allowsInvoiceManagementBy($this->user())) {
            return $rules;
        }

        return [
            ...$rules,
            'billing_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'billing_contact_id' => [
                'nullable',
                'integer',
                Rule::exists('client_contacts', 'id')->where('client_id', $client instanceof Client ? $client->id : 0),
            ],
            'billed_through_client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where('organization_id', $organization->id),
                Rule::notIn($client instanceof Client ? [$client->id] : []),
                Rule::exists('clients', 'id')->whereNull('billed_through_client_id'),
                fn (string $attribute, mixed $value, Closure $fail) => $client instanceof Client && $value !== null && $client->billedClients()->exists()
                    ? $fail(__('This client already bills other clients.'))
                    : null,
            ],
        ];
    }
}
