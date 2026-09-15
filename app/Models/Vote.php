<?php

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A vote for (direction 1) or against (direction -1) a feature request.
 *
 * @property int $id
 * @property int $feature_id
 * @property int $user_id
 * @property int $direction
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Feature $feature
 * @property-read User $user
 */
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
