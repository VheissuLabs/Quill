<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @mixin IdeHelperMembership */

class Membership extends Pivot
{
    public $incrementing = true;

    protected $table = 'team_members';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }
}
