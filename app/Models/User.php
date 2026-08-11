<?php

namespace App\Models;

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

/** @mixin IdeHelperUser */

#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements PasskeyUser
{
    use HasFactory, HasNotificationFeed, HasOrganizations, HasTeams, HasUuids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
