<?php

namespace App\Observers;

use App\Models\EmailAttachment;
use App\Models\EmailMessage;

class EmailMessageObserver
{
    public function saving(EmailMessage $message): void
    {
        $message->message_id = EmailMessage::normalizeId($message->message_id);
        $message->in_reply_to = $message->in_reply_to === null ? null : EmailMessage::normalizeId($message->in_reply_to);
    }

    public function deleting(EmailMessage $message): void
    {
        $message->attachments()->get()->each(fn (EmailAttachment $attachment) => $attachment->delete());
    }
}
