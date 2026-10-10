<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Rules\NotFreeMailDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientDomainRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => strtolower(trim(ltrim($this->string('domain')->toString(), '@'))),
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $client = $this->route('client');

        abort_if(! $client instanceof Client, 404);

        return [
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                new NotFreeMailDomain,
                Rule::unique('client_domains', 'domain')->where('organization_id', $client->organization_id),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'domain.regex' => __('Enter a domain such as acme.com.'),
            'domain.unique' => __('Another client already uses that domain.'),
        ];
    }
}
