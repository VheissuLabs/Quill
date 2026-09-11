<?php

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
#[Fillable(['feature_id', 'user_id', 'direction'])]
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    /**
     * Get the feature that was voted for.
     *
     * @return BelongsTo<Feature, $this>
     */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * Get the user who cast the vote.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
