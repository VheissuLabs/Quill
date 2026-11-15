<?php

namespace App\Http\Requests\Issues;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class IssueLookupRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'except' => ['nullable', 'integer'],
            'invoice' => ['nullable', 'integer', Rule::exists('invoices', 'id')->where('organization_id', $this->route('current_organization')?->id ?? 0)],
        ];
    }

    /** @return Collection<int, int>|null */
    public function billableClientIds(): ?Collection
    {
        $invoice = $this->integer('invoice');

        return $invoice === 0 ? null : Invoice::query()->findOrFail($invoice)->client->billableClientIds();
    }

    public function term(): string
    {
        return trim($this->string('q')->toString());
    }
}
