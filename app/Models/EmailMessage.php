<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailDirection;
use App\Observers\EmailMessageObserver;
use Database\Factories\EmailMessageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @mixin IdeHelperEmailMessage */
#[ObservedBy(EmailMessageObserver::class)]
#[UseFactory(EmailMessageFactory::class)]
class EmailMessage extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function normalizeId(string $messageId): string
    {
        return trim($messageId, " \t\n\r<>");
    }

    /** @param  array<int, string>  $messageIds */
    public static function ticketReferencedBy(array $messageIds): ?Ticket
    {
        $commentable = self::query()
            ->whereIn('message_id', array_map(self::normalizeId(...), $messageIds))
            ->whereHas('comment', fn ($query) => $query->where('commentable_type', (new Ticket)->getMorphClass()))
            ->latest('id')
            ->first()
            ?->comment
            ->commentable;

        return $commentable instanceof Ticket ? $commentable : null;
    }

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** @return HasMany<EmailAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function markSent(): void
    {
        $this->update(['delivery_status' => EmailDeliveryStatus::Sent]);
    }

    public function markFailed(): void
    {
        $this->update(['delivery_status' => EmailDeliveryStatus::Failed]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => EmailDirection::class,
            'delivery_status' => EmailDeliveryStatus::class,
            'to' => 'array',
            'cc' => 'array',
        ];
    }
}
