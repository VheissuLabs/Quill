<?php

namespace App\Models;

use App\Enums\IssueLinkType;
use App\Observers\IssueLinkObserver;
use Database\Factories\IssueLinkFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperIssueLink */
#[ObservedBy(IssueLinkObserver::class)]
#[UseFactory(IssueLinkFactory::class)]
class IssueLink extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Issue, $this> */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /** @return BelongsTo<Issue, $this> */
    public function relatedIssue(): BelongsTo
    {
        return $this->belongsTo(Issue::class, 'related_issue_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => IssueLinkType::class,
        ];
    }
}
