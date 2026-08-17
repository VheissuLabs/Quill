<?php

namespace App\Models;

use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** @mixin IdeHelperOrganizationInvitation */

#[UseFactory(OrganizationInvitationFactory::class)]
class OrganizationInvitation extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['email', 'role'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('organization');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OrganizationInvitation $invitation) {
            if (empty($invitation->code)) {
                $invitation->code = Str::random(64);
            }
        });
    }

    protected function casts(): array
    {
        return [

            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
