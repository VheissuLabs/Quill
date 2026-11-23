<?php

namespace App\Models;

use App\Concerns\BroadcastsChanges;
use App\Events\MembersChanged;
use App\Observers\OrganizationInvitationObserver;
use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role;

/** @mixin IdeHelperOrganizationInvitation */
#[UseFactory(OrganizationInvitationFactory::class)]
#[ObservedBy(OrganizationInvitationObserver::class)]
class OrganizationInvitation extends Model
{
    use BroadcastsChanges, HasFactory, LogsActivity;

    protected $guarded = [];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @param  Builder<$this>  $query */
    #[Scope]
    protected function pendingFor(Builder $query, User $user): void
    {
        $query->with(['inviter', 'organization'])
            ->whereIn(DB::raw('LOWER(email)'), $user->verifiedEmailAddresses())
            ->whereNull('accepted_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->latest();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('organization_invitations')
            ->logOnly(['organization_id', 'email', 'role_id', 'invited_by', 'expires_at', 'accepted_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function changeBroadcast(): MembersChanged
    {
        return new MembersChanged($this->organization_id);
    }
}
