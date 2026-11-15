<?php

namespace App\Http\Requests\Billing;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceLineOrderRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $invoice = $this->route('invoice');

        abort_if(! $invoice instanceof Invoice, 404);

        return [
            'line_ids' => ['required', 'array', 'size:'.$invoice->lines()->count()],
            'line_ids.*' => ['integer', 'distinct', Rule::exists('invoice_lines', 'id')->where('invoice_id', $invoice->id)],
        ];
    }
}
