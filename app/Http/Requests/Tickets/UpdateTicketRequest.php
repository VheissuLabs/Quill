<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketStatus;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organization = $this->route('current_organization');

        abort_if(! $organization instanceof Organization, 404);

        return [
            'status' => ['sometimes', 'required', Rule::enum(TicketStatus::class)],
            'client_id' => ['sometimes', 'nullable', 'integer', Rule::exists('clients', 'id')->where('organization_id', $organization->id)],
        ];
    }
}
