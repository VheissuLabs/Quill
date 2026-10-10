<?php

namespace App\Http\Requests\Tickets;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'client_contact_id' => ['nullable', 'integer', Rule::exists('client_contacts', 'id')->where('organization_id', $organization->id)],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organization->id)],
            'requester_name' => ['required_without:client_contact_id', 'nullable', 'string', 'max:255'],
            'requester_email' => [
                'required_without:client_contact_id',
                'nullable',
                'email',
                'max:255',
                Rule::when(
                    $this->filled('client_id') && ! $this->filled('client_contact_id'),
                    [Rule::unique('client_contacts', 'email')->where('organization_id', $organization->id)],
                ),
            ],
            'subject' => ['required', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:20000'],
            'send' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'requester_name.required_without' => __('Pick a contact or enter the customer\'s name.'),
            'requester_email.required_without' => __('Pick a contact or enter the customer\'s email.'),
            'requester_email.unique' => __('That email already belongs to a contact. Pick them from the list.'),
        ];
    }

    /** @return array{client_contact_id: int|null, client_id: int|null, requester_name: string|null, requester_email: string|null, subject: string, body: string, send: bool} */
    public function ticketAttributes(): array
    {
        return [
            'client_contact_id' => $this->validated('client_contact_id'),
            'client_id' => $this->validated('client_id'),
            'requester_name' => $this->validated('requester_name'),
            'requester_email' => $this->validated('requester_email'),
            'subject' => $this->validated('subject'),
            'body' => $this->validated('body'),
            'send' => $this->boolean('send'),
        ];
    }
}
