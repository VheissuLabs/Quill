<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuids;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function label(): string
    {
        return Str::headline($this->name);
    }
}
