<?php

namespace App\Models;

use App\Observers\OrganizationModuleSettingObserver;
use Database\Factories\OrganizationModuleSettingFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @mixin IdeHelperOrganizationModuleSetting */
#[ObservedBy(OrganizationModuleSettingObserver::class)]
#[UseFactory(OrganizationModuleSettingFactory::class)]
class OrganizationModuleSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
