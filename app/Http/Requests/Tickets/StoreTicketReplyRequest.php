<?php

namespace App\Http\Requests\Tickets;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketReplyRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $ticket = $this->route('ticket');

        abort_if(! $ticket instanceof Ticket, 404);

        return [
            'body' => ['required', 'string', 'max:20000'],
            'internal' => ['boolean'],
            'mentions' => ['array', 'max:20'],
            'mentions.*' => ['integer', 'distinct', Rule::in($ticket->mentionableUsers()->pluck('id'))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mentions.*.in' => __('You can only mention people in this organization.'),
        ];
    }
}
