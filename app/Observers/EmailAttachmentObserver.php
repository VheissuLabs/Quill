<?php

namespace App\Observers;

use App\Models\EmailAttachment;
use Illuminate\Support\Facades\Storage;

class EmailAttachmentObserver
{
    public function deleted(EmailAttachment $attachment): void
    {
        Storage::delete($attachment->path);
    }
}
