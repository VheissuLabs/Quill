<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\WorkType;
use Illuminate\Http\Request;

/** @mixin Invoice */
class InvoiceResource extends InvoiceListResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::toArray($request),
            'projectId' => $this->project_id,
            'sprintId' => $this->sprint_id,
            'contactId' => $this->contact_id,
            'ccEmails' => $this->cc_emails ?? [],
            'servicePeriodStart' => $this->service_period_start?->toDateString(),
            'servicePeriodEnd' => $this->service_period_end?->toDateString(),
            'subtotal' => $this->subtotal,
            'taxRate' => $this->tax_rate,
            'taxAmount' => $this->tax_amount,
            'discountType' => $this->discount_type?->value,
            'discountValue' => $this->discount_value,
            'discountAmount' => $this->discount_amount,
            'depositType' => $this->deposit_type?->value,
            'depositValue' => $this->deposit_value,
            'depositAmount' => $this->deposit_amount,
            'amountPaid' => $this->amount_paid,
            'notes' => $this->notes,
            'terms' => $this->terms,
            'sentAt' => $this->sent_at?->toIso8601String(),
            'closesAt' => $this->closes_at?->toIso8601String(),
            'voidedAt' => $this->voided_at?->toIso8601String(),
            'publicUrl' => $this->when($this->sent_at !== null, fn () => $this->publicUrl()),
            'isEditable' => $this->isEditable(),
            'canUpdate' => $user?->can('update', $this->resource) ?? false,
            'canDelete' => $user?->can('delete', $this->resource) ?? false,
            'canSend' => $user?->can('send', $this->resource) ?? false,
            'canVoid' => $user?->can('void', $this->resource) ?? false,
            'canDuplicate' => $user?->can('duplicate', $this->resource) ?? false,
            'lines' => $this->lines->map(fn (InvoiceLine $line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit' => $line->unit->value,
                'rate' => $line->rate,
                'amount' => $line->amount,
                'workTypeId' => $line->work_type_id,
                'issueId' => $line->issue_id,
                'issueReference' => $line->issue?->reference(),
                'referenceUrl' => $line->reference_url,
            ])->all(),
            'payments' => $this->payments->map(fn (InvoicePayment $payment) => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'paidOn' => $payment->paid_on->toDateString(),
                'method' => $payment->method->value,
                'methodLabel' => $payment->method->label(),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
            ])->all(),
            'options' => [
                'paymentMethods' => array_map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::cases()),
                'contacts' => $this->client->contacts()->orderBy('name')->get(['id', 'name', 'email'])
                    ->map(fn (ClientContact $contact) => ['id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email])->all(),
                'projects' => Project::whereIn('client_id', $this->client->billableClientIds())->orderBy('name')->get(['id', 'name', 'slug'])
                    ->map(fn (Project $project) => ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug])->all(),
                'sprints' => $this->organization->allSprints()->orderByDesc('starts_at')->get(['id', 'name'])
                    ->map(fn (Sprint $sprint) => ['id' => $sprint->id, 'name' => $sprint->name])->all(),
                'workTypes' => $this->organization->workTypes()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                    ->map(fn (WorkType $workType) => ['id' => $workType->id, 'name' => $workType->name])->all(),
            ],
        ];
    }
}
