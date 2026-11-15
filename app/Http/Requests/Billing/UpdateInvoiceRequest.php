<?php

namespace App\Http\Requests\Billing;

use App\Concerns\InvoiceHeaderRules;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    use InvoiceHeaderRules;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');
        $invoice = $this->route('invoice');

        abort_if(! $organization instanceof Organization || ! $invoice instanceof Invoice, 404);

        $rules = $this->headerRules($organization, $invoice->client);

        if ($invoice->isEditable()) {
            return $rules;
        }

        foreach (array_keys($this->moneyRules()) as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $message = __('This invoice has been sent; its amounts can no longer change.');

        return collect(array_keys($this->moneyRules()))->mapWithKeys(fn (string $field) => ["{$field}.prohibited" => $message])->all();
    }
}
