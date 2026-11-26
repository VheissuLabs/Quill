<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Events\IssuesChanged;
use App\Events\ProjectsChanged;
use App\Observers\DocumentObserver;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;

/** @mixin IdeHelperDocument */
#[UseFactory(DocumentFactory::class)]
#[ObservedBy(DocumentObserver::class)]
class Document extends Model
{
    use BroadcastsChanges, HasFactory;

    public const ACCEPTED_TYPES = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'heic'];

    public const MAX_KILOBYTES = 20480;

    protected $guarded = [];

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function storeFor(Model $owner, int $organizationId, UploadedFile $file, ?User $uploader): self
    {
        return $owner->morphMany(self::class, 'documentable')->create([
            'organization_id' => $organizationId,
            'user_id' => $uploader?->id,
            'name' => $file->getClientOriginalName(),
            'path' => $file->store("documents/{$organizationId}"),
            'content_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->content_type, 'image/');
    }

    public function changeBroadcast(): IssuesChanged|ProjectsChanged
    {
        return $this->documentable_type === (new Issue)->getMorphClass()
            ? new IssuesChanged($this->organization_id, [$this->documentable_id])
            : new ProjectsChanged($this->organization_id);
    }
}
