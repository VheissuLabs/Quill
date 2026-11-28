<?php

namespace App\Http\Requests\Tickets;

use App\Models\Ticket;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConvertTicketRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $ticket = $this->route('ticket');

        abort_if(! $ticket instanceof Ticket, 404);

        return [
            'project_id' => ['required_without:issue_id', 'nullable', 'integer', Rule::exists('projects', 'id')
                ->where('organization_id', $ticket->organization_id)
                ->where('client_id', $ticket->client_id)],
            'issue_id' => ['required_without:project_id', 'nullable', 'integer', Rule::exists('issues', 'id')
                ->whereIn('project_id', $ticket->organization->projects()->pluck('id')->all())],
        ];
    }

    /** @return array<int, Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $ticket = $this->route('ticket');

                if ($ticket instanceof Ticket && ! $ticket->isAssigned()) {
                    $validator->errors()->add('client_id', __('Assign this ticket to a client or to :organization first.', ['organization' => $ticket->organization->name]));
                }
            },
        ];
    }
}
