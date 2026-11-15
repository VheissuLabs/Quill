<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\InvoiceLineRequest;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class InvoiceLineController extends Controller
{
    public function store(InvoiceLineRequest $request, Organization $currentOrganization, Invoice $invoice): RedirectResponse
    {
        $attributes = collect($request->validated())->reject(fn (mixed $value, string $key) => $value === null && in_array($key, ['rate', 'unit'], true))->all();

        $attributes['description'] = filled($attributes['description'] ?? null)
            ? $attributes['description']
            : Issue::query()->whereKey($attributes['issue_id'] ?? 0)->value('title');
        $attributes['rate'] ??= $invoice->defaultLineRate();
        $attributes['sort_order'] = ($invoice->lines()->max('sort_order') ?? -1) + 1;

        $invoice->lines()->create($attributes);

        return back();
    }

    public function update(InvoiceLineRequest $request, Organization $currentOrganization, Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        $line->update(collect($request->validated())->reject(fn (mixed $value, string $key) => $value === null && in_array($key, ['rate', 'quantity', 'unit'], true))->all());

        return back();
    }

    public function destroy(Organization $currentOrganization, Invoice $invoice, InvoiceLine $line): RedirectResponse
    {
        $line->delete();

        return back();
    }
}
