<?php

namespace App\Http\Requests\Tickets;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailDirection;
use App\Models\Comment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RetryTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        return $comment instanceof Comment
            && $comment->emailMessage?->direction === EmailDirection::Outbound
            && $comment->emailMessage->delivery_status === EmailDeliveryStatus::Failed;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [];
    }
}
