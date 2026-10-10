<?php

namespace App\Models;

use App\Observers\EmailAttachmentObserver;
use Database\Factories\EmailAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperEmailAttachment */
#[ObservedBy(EmailAttachmentObserver::class)]
#[UseFactory(EmailAttachmentFactory::class)]
class EmailAttachment extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<EmailMessage, $this> */
    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    public function ticket(): ?Ticket
    {
        $commentable = $this->emailMessage->comment->commentable;

        return $commentable instanceof Ticket ? $commentable : null;
    }
}
