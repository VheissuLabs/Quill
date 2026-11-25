<?php

namespace App\Http\Requests\Billing;

use App\Enums\LineUnit;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceLineRequest extends FormRequest
{
    /** @var list<string> */
    private const MONEY_FIELDS = ['quantity', 'unit', 'rate', 'issue_id'];

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');
        $invoice = $this->route('invoice');

        abort_if(! $organization instanceof Organization || ! $invoice instanceof Invoice, 404);

        $creating = $this->isMethod('POST');
        $presence = $creating ? 'required' : 'sometimes';
        $projectIds = Project::query()
            ->where('organization_id', $organization->id)
            ->whereIn('client_id', $invoice->client->billableClientIds())
            ->pluck('id')
            ->all();

        $rules = [
            'description' => $creating
                ? ['required_without:issue_id', 'nullable', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],
            'quantity' => [$presence, 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['sometimes', Rule::enum(LineUnit::class)],
            'rate' => [
                $creating && $invoice->defaultLineRate() === null ? 'required' : 'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'work_type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('work_types', 'id')->where('organization_id', $organization->id)],
            'issue_id' => ['sometimes', 'nullable', 'integer', Rule::exists('issues', 'id')->whereIn('project_id', $projectIds)],
            'reference_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];

        if (! $creating && ! $invoice->isEditable()) {
            foreach (self::MONEY_FIELDS as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function lineAttributes(): array
    {
        return collect($this->validated())->reject(fn (mixed $value, string $key) => $value === null && in_array($key, ['rate', 'quantity', 'unit'], true))->all();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $message = __('This invoice has been sent; its amounts can no longer change.');

        return collect(self::MONEY_FIELDS)->mapWithKeys(fn (string $field) => ["{$field}.prohibited" => $message])->all();
    }
}
