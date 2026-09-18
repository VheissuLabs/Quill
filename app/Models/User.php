<?php

namespace App\Models;

use App\Concerns\HasOrganizations;
use App\Observers\UserObserver;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/** @mixin IdeHelperUser */
#[ObservedBy(UserObserver::class)]
#[UseFactory(UserFactory::class)]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, HasOrganizations, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $guarded = [];

    /** @return HasMany<EmailAddress, $this> */
    public function emailAddresses(): HasMany
    {
        return $this->hasMany(EmailAddress::class);
    }

    /** @return Collection<int, lowercase-string> */
    public function verifiedEmailAddresses(): Collection
    {
        return $this->emailAddresses()
            ->verified()
            ->pluck('email')
            ->prepend($this->email)
            ->map(fn (string $email) => Str::lower($email))
            ->unique()
            ->values();
    }

    public function hasEmailAddress(string $email): bool
    {
        return $this->verifiedEmailAddresses()->contains(strtolower($email));
    }

    /** @return BelongsTo<Team, $this> */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function boardOwnerFor(Organization $organization): Organization|Team
    {
        return $this->currentTeamFor($organization) ?? $organization;
    }

    public function currentTeamFor(Organization $organization): ?Team
    {
        if ($this->currentTeam?->organization_id === $organization->id) {
            return $this->currentTeam;
        }

        $team = $this->teams()->where('organization_id', $organization->id)->orderBy('name')->first();

        $this->update(['current_team_id' => $team?->id]);
        $this->setRelation('currentTeam', $team);

        return $team;
    }

    public function switchTeam(Team $team): bool
    {
        if ($team->organization_id !== $this->current_organization_id) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        return true;
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withTimestamps();
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /** @return HasMany<Feature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /** @return HasMany<Issue, $this> */
    public function assignedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'assignee_id');
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'timezone', 'email_verified_at', 'two_factor_confirmed_at', 'current_organization_id', 'current_team_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
