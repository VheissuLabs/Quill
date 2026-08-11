<?php

namespace App\Models;

use App\Concerns\HasAssistantConversation;
use App\Concerns\HasNotificationFeed;
use App\Concerns\HasOrganizations;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/** @mixin IdeHelperUser */

#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements PasskeyUser
{
    use HasAssistantConversation, HasFactory, HasNotificationFeed, HasOrganizations, HasUuids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Spatie scopes roles by a "team", which in Quill is the organization, so its
     * `teams()` relation means "organizations I hold a role in" — a different
     * thing from Quill's own teams. Ours keeps the name.
     */
    use HasRoles, HasTeams {
        HasTeams::teams insteadof HasRoles;
        HasRoles::teams as roleOrganizations;
    }

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
