<?php

namespace App\Models;

use App\Notifications\VerifyEmailAddress;
use App\Observers\EmailAddressObserver;
use Database\Factories\EmailAddressFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperEmailAddress */
#[ObservedBy(EmailAddressObserver::class)]
#[UseFactory(EmailAddressFactory::class)]
class EmailAddress extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function markAsVerified(): void
    {
        $this->forceFill(['verified_at' => now()])->save();
    }

    public function sendVerificationNotification(): void
    {
        Notification::route('mail', $this->email)->notify(new VerifyEmailAddress($this));
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('email_addresses')
            ->logOnly(['user_id', 'email', 'verified_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
