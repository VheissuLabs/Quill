<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim($this->string('email')->toString()))]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $client = $this->route('client');
        $contact = $this->route('contact');

        abort_if(! $client instanceof Client, 404);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('client_contacts', 'email')
                    ->where('organization_id', $client->organization_id)
                    ->ignore($contact instanceof ClientContact ? $contact->getKey() : null),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => __('That email already belongs to a contact.'),
        ];
    }
}
