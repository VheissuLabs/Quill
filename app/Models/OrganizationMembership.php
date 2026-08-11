<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @mixin IdeHelperOrganizationMembership */

class OrganizationMembership extends Pivot
{
    use HasUuids;

    protected $table = 'organization_members';

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The client this member is a contact for, when the role is `Client`.
     *
     * Null for everyone who works for the organization itself.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
        ];
    }
}
