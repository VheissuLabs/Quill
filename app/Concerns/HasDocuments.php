<?php

namespace App\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocuments
{
    public static function bootHasDocuments(): void
    {
        static::deleting(function (self $owner): void {
            $owner->documents()->each(fn (Document $document) => $document->delete());
        });
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->latest('id');
    }
}
