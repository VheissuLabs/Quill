<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasOrganizations;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $timezone
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_organization_id
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $currentOrganization
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Organization> $ownedOrganizations
 * @property-read Collection<int, OrganizationMembership> $organizationMemberships
 * @property-read Collection<int, Organization> $organizations
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Feature> $features
 * @property-read Collection<int, Vote> $votes
 * @property-read Collection<int, Issue> $assignedIssues
 * @property-read Collection<int, EmailAddress> $emailAddresses
 */
#[Fillable(['name', 'email', 'password', 'timezone', 'current_organization_id', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasOrganizations, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the additional email addresses on the account.
     *
     * @return HasMany<EmailAddress, $this>
     */
    public function emailAddresses(): HasMany
    {
        return $this->hasMany(EmailAddress::class);
    }

    /**
     * Get every address the user is reachable at: the primary email plus verified additional ones.
     *
     * @return \Illuminate\Support\Collection<int, lowercase-string>
     */
    public function verifiedEmailAddresses(): \Illuminate\Support\Collection
    {
        return $this->emailAddresses()
            ->verified()
            ->pluck('email')
            ->prepend($this->email)
            ->map(fn (string $email) => Str::lower($email))
            ->unique()
            ->values();
    }

    /**
     * Determine if the given address is the primary email or a verified additional address.
     */
    public function hasEmailAddress(string $email): bool
    {
        return $this->verifiedEmailAddresses()->contains(strtolower($email));
    }

    /**
     * Get the team the user is currently working in.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get whoever runs the board in this organization: the user's current team, or the
     * organization itself while it has no teams. Teams are opt in.
     */
    public function boardOwnerFor(Organization $organization): Organization|Team
    {
        return $this->currentTeamFor($organization) ?? $organization;
    }

    /**
     * Get the team the user is working in within the given organization. Falls back to the first
     * team they belong to there, then to the organization's first team, and remembers the choice.
     */
    public function currentTeamFor(Organization $organization): ?Team
    {
        if ($this->currentTeam?->organization_id === $organization->id) {
            return $this->currentTeam;
        }

        $team = $this->teams()->where('organization_id', $organization->id)->orderBy('name')->first()
            ?? $organization->teams()->orderBy('name')->first();

        $this->update(['current_team_id' => $team?->id]);
        $this->setRelation('currentTeam', $team);

        return $team;
    }

    /**
     * Switch to the given team. It must belong to the user's current organization.
     */
    public function switchTeam(Team $team): bool
    {
        if ($team->organization_id !== $this->current_organization_id) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        return true;
    }

    /**
     * Get the teams (departments) the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withTimestamps();
    }

    /**
     * Get the features the user has requested.
     *
     * @return HasMany<Feature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /**
     * Get the issues assigned to the user.
     *
     * @return HasMany<Issue, $this>
     */
    public function assignedIssues(): HasMany
    {
        return $this->hasMany(Issue::class, 'assignee_id');
    }

    /**
     * Get the votes the user has cast.
     *
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Log profile, security and context changes. Passwords, secrets and tokens are never logged.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['name', 'email', 'timezone', 'email_verified_at', 'two_factor_confirmed_at', 'current_organization_id', 'current_team_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
