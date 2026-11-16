<?php

namespace App\Http\Requests\Billing;

use App\Concerns\InvoiceHeaderRules;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    use InvoiceHeaderRules;

    protected function prepareForValidation(): void
    {
        $organization = $this->route('current_organization');

        if (! $organization instanceof Organization || $this->filled('client_id') || ! $this->filled('project_id')) {
            return;
        }

        $client = Project::where('organization_id', $organization->id)->find($this->integer('project_id'))?->client;

        if ($client) {
            $this->merge(['client_id' => $client->billed_through_client_id ?? $client->id]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        $client = Client::where('organization_id', $organization->id)->find($this->integer('client_id'));

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organization->id)],
            ...$this->headerRules($organization, $client),
        ];
    }
}
