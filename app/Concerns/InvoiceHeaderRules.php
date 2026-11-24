<?php

namespace App\Concerns;

use App\Enums\AdjustmentType;
use App\Models\Client;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait InvoiceHeaderRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    protected function headerRules(Organization $organization, ?Client $client): array
    {
        $billableClientIds = $client?->billableClientIds()->all() ?? [];

        return array_map(fn (array $rules): array => array_filter($rules), [
            'project_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('organization_id', $organization->id)->whereIn('client_id', $billableClientIds),
            ],
            'sprint_id' => ['sometimes', 'nullable', 'integer', Rule::in($organization->allSprints()->pluck('id')->all())],
            'contact_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('client_contacts', 'id')->where('client_id', $client instanceof Client ? $client->id : 0),
            ],
            'cc_emails' => ['sometimes', 'nullable', 'array'],
            'cc_emails.*' => ['required', 'email:rfc', 'max:255'],
            'issue_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['sometimes', 'required', 'date', $this->filled('issue_date') ? 'after_or_equal:issue_date' : null],
            'service_period_start' => ['sometimes', 'nullable', 'date'],
            'service_period_end' => ['sometimes', 'nullable', 'date', $this->filled('service_period_start') ? 'after_or_equal:service_period_start' : null],
            ...$this->moneyRules(),
        ]);
    }

    /** @return array<string, array<mixed>> */
    protected function moneyRules(): array
    {
        $adjustment = fn (string $field): array => [
            "{$field}_type" => ['sometimes', 'nullable', Rule::enum(AdjustmentType::class)],
            "{$field}_value" => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                $this->input("{$field}_type") === AdjustmentType::Percentage->value ? 'max:100' : null,
            ],
        ];

        return array_map(fn (array $rules): array => array_filter($rules), [
            'tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            ...$adjustment('discount'),
            ...$adjustment('deposit'),
        ]);
    }
}
