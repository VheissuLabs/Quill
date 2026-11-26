<?php

namespace App\Observers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class DocumentObserver
{
    public function deleted(Document $document): void
    {
        Storage::delete($document->path);
    }
}
