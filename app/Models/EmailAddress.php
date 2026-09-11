<?php

namespace App\Models;

use App\Notifications\VerifyEmailAddress;
use Database\Factories\EmailAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * An additional email address on a user's account. Once verified, invitations sent to it
 * reach the account and it can be used to log in.
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'email', 'verified_at'])]
class EmailAddress extends Model
{
    /** @use HasFactory<EmailAddressFactory> */
    use HasFactory;

    /**
     * Get the user the address belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine if the address has been verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Mark the address as verified.
     */
    public function markAsVerified(): void
    {
        $this->forceFill(['verified_at' => now()])->save();
    }

    /**
     * Send the verification link to the address itself.
     */
    public function sendVerificationNotification(): void
    {
        Notification::route('mail', $this->email)->notify(new VerifyEmailAddress($this));
    }

    /**
     * Scope the query to verified addresses.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }
}
