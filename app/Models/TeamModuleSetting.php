<?php

namespace App\Models;

use App\Observers\TeamModuleSettingObserver;
use Database\Factories\TeamModuleSettingFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperTeamModuleSetting */
#[ObservedBy(TeamModuleSettingObserver::class)]
#[UseFactory(TeamModuleSettingFactory::class)]
class TeamModuleSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
