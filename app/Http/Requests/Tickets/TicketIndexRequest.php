<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketIndexRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'view' => ['nullable', 'string', Rule::in(['unassigned', 'ready', 'mine'])],
        ];
    }

    /** @return array{status: string|null, client: string|null, view: string|null} */
    public function filters(): array
    {
        return [
            'status' => TicketStatus::tryFrom((string) $this->query('status'))?->value,
            'client' => $this->string('client')->toString() ?: null,
            'view' => $this->string('view')->toString() ?: null,
        ];
    }
}
